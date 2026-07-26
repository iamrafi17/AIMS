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
        $users = User::with(['college:id,name,code', 'program:id,college_id,name,code'])
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->search.'%')
                        ->orWhere('email', 'like', '%'.$request->search.'%')
                        ->orWhere('staff_id', 'like', '%'.$request->search.'%');
                });
            })
            ->when($request->role, function ($query) use ($request) {
                $query->where('role', $request->role);
            })
            ->latest()
            ->paginate(15);

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
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

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function updateRole(Request $request, User $user)
    {
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

    public function toggleStatus(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'message' => 'User status updated successfully',
            'user' => $user,
        ]);
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user->update(['password' => Hash::make($request->password)]);

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
