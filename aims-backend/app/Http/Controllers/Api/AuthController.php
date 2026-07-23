<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OjtEnrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

        $user = User::query()
            ->with('student')
            ->where('email', $request->login)
            ->orWhereHas('student', function ($query) use ($request) {
                $query->where('student_id', $request->login);
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
                'role' => $user->role,
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
            'college_id' => 'required|exists:colleges,id',
            'program_id' => 'required|exists:programs,id',
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
                'college_id' => $request->college_id,
                'program_id' => $request->program_id,
                'year_level' => $request->year_level,
                'section' => $enrollment->section,
                'parent_name' => $request->parent_name,
                'parent_relationship' => $request->parent_relationship,
                'parent_address' => $request->parent_address,
                'parent_phone' => $request->parent_phone,
                'hte_id' => $request->hte_id,
                'internship_semester' => $request->internship_semester,
                'internship_year' => $request->internship_year,
            ]);

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
        $enrollment = OjtEnrollment::where('school_id', trim($schoolId))->first();

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
                'role' => $user->role,
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

        if ($user) {
            // In production, send email with reset link
            // For now, just return success message
        }

        return response()->json([
            'message' => 'If the email exists, a password reset link has been sent.',
        ]);
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
                'role' => $user->role,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar_url,
            ],
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
