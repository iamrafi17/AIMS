<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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

            $user->student?->update([
                'phone' => $validated['phone'] ?? $user->student->phone,
                'address' => $validated['address'] ?? $user->student->address,
            ]);
        });

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->userPayload($user),
        ]);
    }

    public function updateAccount(Request $request)
    {
        $user = $request->user();
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

    public function updateContact(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->update($validated);
            $user->student?->update([
                'phone' => $validated['phone'],
                'address' => $validated['address'],
            ]);
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
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:current_password'],
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
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'staff_id' => $user->staff_id,
            'phone' => $user->phone,
            'address' => $user->address,
            'role' => $user->role,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
