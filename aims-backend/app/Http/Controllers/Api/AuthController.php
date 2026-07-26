<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\OjtEnrollment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required',
            'device_name' => 'required|string',
        ]);

        $identifier = trim($request->login);

        $user = User::query()
            ->with('student')
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhere('staff_id', strtoupper($identifier))
                    ->orWhereHas('student', function ($studentQuery) use ($identifier) {
                        $studentQuery->where('student_id', $identifier);
                    });
            })
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Your account has been deactivated.'],
            ]);
        }

        if ($user->role === 'student' && $user->student?->registration_status !== 'approved') {
            $message = $user->student?->registration_status === 'rejected'
                ? 'Your registration was not approved. Please contact your internship coordinator.'
                : 'Your registration is still awaiting coordinator approval.';

            throw ValidationException::withMessages(['login' => [$message]]);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken($request->device_name)->plainTextToken;

        $student = $user->student;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->staff_id,
                'phone' => $user->phone,
                'address' => $user->address,
                'role' => $user->role,
                'college' => $user->college,
                'program' => $user->program,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar_url,
            ],
            'student' => $student ? [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'college' => $student->college,
                'program' => $student->program,
                'year_level' => $student->year_level,
                'section' => $student->section,
                'hte' => $student->hte,
                'internship_status' => $student->internship_status,
            ] : null,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'student_id' => 'required|string|unique:students,student_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female',
            'birth_date' => 'required|date',
            'address' => 'required|string',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|unique:users,email',
            'college_id' => 'nullable|exists:colleges,id',
            'program_id' => 'nullable|exists:programs,id',
            'year_level' => 'required|integer|between:4,5',
            'section' => 'required|string|max:50',
            'parent_name' => 'required|string|max:255',
            'parent_relationship' => 'required|string|max:50',
            'parent_address' => 'required|string',
            'parent_phone' => 'required|string|max:20',
            'hte_id' => 'nullable|exists:htes,id',
            'internship_semester' => 'required|string|max:30',
            'internship_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            'agree_terms' => 'accepted',
            'agree_privacy' => 'accepted',
            'password' => 'required|string|min:8|confirmed',
        ]);

        [$user, $student] = DB::transaction(function () use ($request) {
            $enrollment = OjtEnrollment::where('school_id', $request->student_id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'student_id' => ['Your School ID is not included in the coordinator OJT enrollment list.'],
                ]);
            }

            if ($enrollment->registered_at || $enrollment->student_record_id) {
                throw ValidationException::withMessages([
                    'student_id' => ['An account has already been registered for this School ID.'],
                ]);
            }

            if (! $enrollment->college_id || ! $enrollment->program_id) {
                throw ValidationException::withMessages([
                    'student_id' => ['Your official OJT enrollment has no academic program assignment. Contact your coordinator.'],
                ]);
            }

            if ($request->hte_id && ! HTE::whereKey($request->hte_id)->where('program_id', $enrollment->program_id)->exists()) {
                throw ValidationException::withMessages([
                    'hte_id' => ['The selected HTE is not available for your enrolled program.'],
                ]);
            }

            [$firstName, $lastName] = $this->splitEnrollmentName($enrollment->full_name);

            $user = User::create([
                'name' => $enrollment->full_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'student',
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'student_id' => $request->student_id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_name' => $request->middle_name,
                'gender' => $request->gender,
                'birth_date' => $request->birth_date,
                'address' => $request->address,
                'phone' => $request->phone,
                'college_id' => $enrollment->college_id,
                'program_id' => $enrollment->program_id,
                'year_level' => $request->year_level,
                'section' => $enrollment->section,
                'required_ojt_hours' => $enrollment->college?->required_ojt_hours ?? 486,
                'parent_name' => $request->parent_name,
                'parent_relationship' => $request->parent_relationship,
                'parent_address' => $request->parent_address,
                'parent_phone' => $request->parent_phone,
                'hte_id' => $request->hte_id,
                'internship_semester' => $request->internship_semester,
                'internship_year' => $request->internship_year,
            ]);

            InternshipRequirement::ensureForStudent($student->id);

            $enrollment->update([
                'student_record_id' => $student->id,
                'registered_at' => now(),
            ]);

            return [$user, $student];
        });

        return response()->json([
            'message' => 'Registration successful. Please wait for approval.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
            ],
        ], 201);
    }

    public function enrollment(string $schoolId)
    {
        $enrollment = OjtEnrollment::with(['college:id,name,code,required_ojt_hours', 'program:id,college_id,name,code'])
            ->where('school_id', trim($schoolId))
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'School ID was not found in the coordinator OJT enrollment list.',
            ], 404);
        }

        if ($enrollment->registered_at || $enrollment->student_record_id) {
            return response()->json([
                'message' => 'An account has already been registered for this School ID.',
            ], 409);
        }

        [$firstName, $lastName] = $this->splitEnrollmentName($enrollment->full_name);

        return response()->json([
            'student_id' => $enrollment->school_id,
            'full_name' => $enrollment->full_name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'section' => $enrollment->section,
            'college' => $enrollment->college,
            'program' => $enrollment->program,
            'required_ojt_hours' => (float) ($enrollment->college?->required_ojt_hours ?? 486),
        ]);
    }

    private function splitEnrollmentName(string $fullName): array
    {
        $name = preg_replace('/\s+/', ' ', trim($fullName));
        $parts = explode(' ', $name);
        $lastName = count($parts) > 1 ? array_pop($parts) : '';
        $firstName = implode(' ', $parts) ?: $name;

        return [$firstName, $lastName];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $student = $user->student;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->staff_id,
                'phone' => $user->phone,
                'address' => $user->address,
                'role' => $user->role,
                'college' => $user->college,
                'program' => $user->program,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar_url,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at,
            ],
            'student' => $student ? [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'middle_name' => $student->middle_name,
                'gender' => $student->gender,
                'birth_date' => $student->birth_date,
                'address' => $student->address,
                'phone' => $student->phone,
                'college' => $student->college,
                'program' => $student->program,
                'year_level' => $student->year_level,
                'section' => $student->section,
                'parent_name' => $student->parent_name,
                'parent_address' => $student->parent_address,
                'parent_phone' => $student->parent_phone,
                'hte' => $student->hte,
                'internship_status' => $student->internship_status,
                'registration_status' => $student->registration_status,
            ] : null,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        $plainToken = null;
        if ($user) {
            $plainToken = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($plainToken), 'created_at' => now()],
            );
        }

        return response()->json(array_filter([
            'message' => 'If the email exists, a password reset link has been sent.',
            'reset_token' => app()->environment('local') ? $plainToken : null,
        ]));
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $record = DB::table('password_reset_tokens')->where('email', $validated['email'])->first();

        if (
            ! $record
            || ! Hash::check($validated['token'], $record->token)
            || now()->diffInMinutes(Carbon::parse($record->created_at)) > 60
        ) {
            throw ValidationException::withMessages(['token' => ['This password reset token is invalid or expired.']]);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        DB::transaction(function () use ($user, $validated) {
            $user->update(['password' => Hash::make($validated['password'])]);
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });

        return response()->json(['message' => 'Password reset successfully. You may now sign in.']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? $user->phone,
                'address' => $validated['address'] ?? $user->address,
            ]);

            if ($user->student) {
                $user->student->update([
                    'phone' => $validated['phone'] ?? $user->student->phone,
                    'address' => $validated['address'] ?? $user->student->address,
                ]);
            }
        });

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->staff_id,
                'phone' => $user->phone,
                'address' => $user->address,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function updateAccountInformation(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Account information updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->staff_id,
                'phone' => $user->phone,
                'address' => $user->address,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function updateProfileInformation(Request $request)
    {
        $user = $request->user();
        $student = $user->student;

        if (! $student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['required', 'date', 'before:today'],
        ]);

        DB::transaction(function () use ($user, $student, $validated) {
            $student->update($validated);
            $user->update([
                'name' => trim(collect([
                    $validated['first_name'],
                    $validated['middle_name'] ?? null,
                    $validated['last_name'],
                ])->filter()->implode(' ')),
            ]);
        });

        return response()->json([
            'message' => 'Profile information updated successfully.',
            'student' => $student->fresh(),
        ]);
    }

    public function updateContactInformation(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
            ]);

            if ($user->student) {
                $user->student->update([
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Contact information updated successfully.',
            'email' => $user->fresh()->email,
            'phone' => $user->student?->fresh()->phone ?? $user->fresh()->phone,
            'address' => $user->student?->fresh()->address ?? $user->fresh()->address,
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $oldAvatar = $user->avatar;
        $path = $validated['avatar']->store('avatars/'.$user->id, 'public');

        $user->update(['avatar' => $path]);

        if ($oldAvatar && $oldAvatar !== $path) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return response()->json([
            'message' => 'Profile photo updated successfully.',
            'avatar' => $path,
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json([
            'message' => 'Profile photo removed.',
            'avatar' => null,
            'avatar_url' => null,
        ]);
    }
}
