<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['student', 'coordinator', 'program_head', 'vpaa', 'admin', 'supervisor'])],
            'college_id' => ['nullable', 'integer', Rule::exists('colleges', 'id')],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
        ]);

        $users = User::with(['college:id,name,code', 'program:id,college_id,name,code'])
            ->when($filters['search'] ?? null, function ($query) use ($filters) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%')
                        ->orWhere('staff_id', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['role'] ?? null, function ($query) use ($filters) {
                $query->where('role', $filters['role']);
            })
            ->when($filters['college_id'] ?? null, fn ($query) => $query->where('college_id', $filters['college_id']))
            ->latest()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'role' => 'required|in:student,coordinator,program_head,vpaa,admin,supervisor',
            ...$this->assignmentRules($request),
        ]);
        $this->validateAssignment($data);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            ...$this->assignmentAttributes($data),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load(['college:id,name,code', 'program:id,college_id,name,code']),
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load(['college:id,name,code', 'program:id,college_id,name,code']));
    }

    public function update(Request $request, User $user)
    {
        abort_if(
            $request->user()->is($user)
            && $request->filled('role')
            && $request->input('role') !== $user->role,
            422,
            'You cannot change the role of your current account.',
        );

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'sometimes|in:student,coordinator,program_head,vpaa,admin,supervisor',
            ...$this->assignmentRules($request, $user),
        ]);
        $data['role'] = $data['role'] ?? $user->role;
        $this->validateAssignment($data);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            ...$this->assignmentAttributes($data),
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->load(['college:id,name,code', 'program:id,college_id,name,code']),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'You cannot deactivate your current account.');
        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json(['message' => 'User account deactivated. Related historical records were preserved.']);
    }

    public function updateRole(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'You cannot change the role of your current account.');
        $data = $request->validate([
            'role' => 'required|in:student,coordinator,program_head,vpaa,admin,supervisor',
            ...$this->assignmentRules($request, $user),
        ]);
        $this->validateAssignment($data);

        $user->update([
            'role' => $data['role'],
            ...$this->assignmentAttributes($data),
        ]);

        return response()->json([
            'message' => 'User role updated successfully',
            'user' => $user->load(['college:id,name,code', 'program:id,college_id,name,code']),
        ]);
    }

    public function toggleStatus(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'You cannot change the status of your current account.');
        $user->update(['is_active' => ! $user->is_active]);
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'User status updated successfully',
            'user' => $user,
        ]);
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:8|max:255',
        ]);

        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset successfully']);
    }

    private function assignmentRules(Request $request, ?User $user = null): array
    {
        $role = $request->input('role', $user?->role);
        $scoped = in_array($role, ['coordinator', 'program_head'], true);

        return [
            'college_id' => [$scoped ? 'required' : 'nullable', 'integer', Rule::exists('colleges', 'id')],
            'program_id' => [$scoped ? 'required' : 'nullable', 'integer', Rule::exists('programs', 'id')],
        ];
    }

    private function validateAssignment(array $data): void
    {
        if (! in_array($data['role'], ['coordinator', 'program_head'], true)) {
            return;
        }

        $valid = Program::query()
            ->whereKey($data['program_id'])
            ->where('college_id', $data['college_id'])
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'program_id' => ['The selected program does not belong to the selected college.'],
            ]);
        }
    }

    private function assignmentAttributes(array $data): array
    {
        if (! in_array($data['role'], ['coordinator', 'program_head'], true)) {
            return ['college_id' => null, 'program_id' => null];
        }

        return [
            'college_id' => $data['college_id'],
            'program_id' => $data['program_id'],
        ];
    }
}
