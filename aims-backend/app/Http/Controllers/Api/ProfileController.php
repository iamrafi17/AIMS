<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => [$user->isStudent() ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'digits:11'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            if ($user->isStudent() && $user->student) {
                $user->update(['email' => $validated['email']]);
                $user->student->update([
                    'contact_number' => $validated['contact_number'] ?? $user->student->contact_number,
                    'address' => $validated['address'] ?? $user->student->address,
                ]);

                return;
            }

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'contact_number' => $validated['contact_number'] ?? $user->contact_number,
                'address' => $validated['address'] ?? $user->address,
            ]);
        });

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->userPayload($user->fresh('student')),
        ]);
    }

    public function updateAccount(Request $request)
    {
        $user = $request->user();

        if ($user->isStudent()) {
            throw ValidationException::withMessages([
                'name' => ['Update a student name through the student information form.'],
            ]);
        }

        $user->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]));

        return response()->json([
            'message' => 'Account information updated successfully.',
            'user' => $this->userPayload($user),
        ]);
    }

    public function updateStudentInformation(Request $request)
    {
        $user = $request->user();
        $student = $user->student;
        abort_unless($student, 404, 'Student profile not found.');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['required', 'date', 'before:today'],
        ]);

        DB::transaction(function () use ($student, $validated) {
            $student->update($validated);
        });

        return response()->json([
            'message' => 'Profile information updated successfully.',
            'student' => $student->fresh(),
        ]);
    }

    public function updateContact(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'contact_number' => ['required', 'string', 'digits:11'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            if ($user->isStudent() && $user->student) {
                $user->update(['email' => $validated['email']]);
                $user->student->update([
                    'contact_number' => $validated['contact_number'],
                    'address' => $validated['address'],
                ]);

                return;
            }

            $user->update($validated);
        });

        $user = $user->fresh('student');

        return response()->json([
            'message' => 'Contact information updated successfully.',
            'email' => $user->email,
            'contact_number' => $user->isStudent() && $user->student ? $user->student->contact_number : $user->contact_number,
            'address' => $user->isStudent() && $user->student ? $user->student->address : $user->address,
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'password' => [...SystemSetting::passwordRules(), 'confirmed', 'different:current_password'],
        ]);
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        return response()->json(['message' => 'Password changed successfully.']);
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

    private function userPayload(User $user): array
    {
        $student = $user->isStudent()
            ? ($user->relationLoaded('student') ? $user->student : $user->student()->first())
            : null;

        return [
            'id' => $user->id,
            'name' => $student?->full_name ?? $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'contact_number' => $student?->contact_number ?? $user->contact_number,
            'address' => $student?->address ?? $user->address,
            'role' => $user->role,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar_url,
        ];
    }

}
