<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Supervisor;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\UserId;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['student', 'coordinator', 'program_head', 'dean', 'vpaa', 'admin', 'supervisor'])],
            'college_id' => ['nullable', 'integer', Rule::exists('colleges', 'id')],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
        ]);

        $users = User::with([
            'roleDefinition:id,code,name',
            'employeeProfile.activeProgramAssignment.program.college',
            'employeeProfile.college',
            'normalizedSupervisorProfile.activeAssignment.department.hte',
            'college:id,name,code',
            'program:id,college_id,name,code',
            'student:id,user_id,first_name,middle_name,last_name',
            'student.college:id,name,code',
            'student.program:id,college_id,name,code',
            'supervisorProfile.department.hte:id,name,address',
        ])
            ->when($filters['search'] ?? null, function ($query) use ($filters) {
                $query->where(function ($q) use ($filters) {
                    $q->where('email', 'like', '%'.$filters['search'].'%')
                        ->orWhere('username', 'like', '%'.$filters['search'].'%');
                    $q->orWhereHas('employeeProfile', function ($profiles) use ($filters): void {
                        foreach (preg_split('/\s+/u', trim($filters['search'])) as $part) {
                            $profiles->where(fn ($name) => $name
                                ->where('first_name', 'like', '%'.$part.'%')
                                ->orWhere('middle_name', 'like', '%'.$part.'%')
                                ->orWhere('last_name', 'like', '%'.$part.'%'));
                        }
                    });
                    $q->orWhereHas('normalizedSupervisorProfile', fn ($profiles) => $profiles
                        ->where('full_name', 'like', '%'.$filters['search'].'%'));
                    $q->orWhereHas('student', fn ($students) => $students
                        ->where('first_name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('middle_name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('last_name', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($filters['role'] ?? null, function ($query) use ($filters) {
                $query->forRole($filters['role']);
            })
            ->when($filters['college_id'] ?? null, function ($query) use ($filters) {
                $query->where(function ($collegeQuery) use ($filters) {
                    $collegeQuery->whereHas(
                        'employeeProfile.activeProgramAssignment.program',
                        fn ($programQuery) => $programQuery->where('college_id', $filters['college_id']),
                    )
                        ->orWhereHas('employeeProfile', fn ($profile) => $profile->where('college_id', $filters['college_id']))
                        ->orWhereHas('student', fn ($studentQuery) => $studentQuery
                            ->forCollege($filters['college_id']));
                });
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        $users->getCollection()->each(function (User $user): void {
            if ($user->role === 'dean') {
                $user->setRelation('college', $user->employeeProfile?->college);
                return;
            }
            if (! $user->isStudent() || ! $user->student) {
                return;
            }

            $user->setRelation('college', $user->student->college);
            $user->setRelation('program', $user->student->program);
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [$request->input('role') === 'supervisor' ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => 'required|in:student,coordinator,program_head,dean,vpaa,admin,supervisor',
            'supervisor_record_id' => [$request->input('role') === 'supervisor' ? 'required' : 'nullable', 'integer', 'exists:hte_supervisor_assignments,id'],
            'is_active' => ['nullable', 'boolean'],
            ...$this->assignmentRules($request),
        ]);
        $this->validateAssignment($data);
        $temporaryPassword = $this->generateTemporaryPassword();

        $user = DB::transaction(function () use ($data, $temporaryPassword): User {
            $supervisor = null;
            if ($data['role'] === 'supervisor') {
                $supervisor = Supervisor::with(['department.hte', 'supervisorProfile'])
                    ->lockForUpdate()
                    ->findOrFail($data['supervisor_record_id']);

                if ($supervisor->user_id) {
                    throw ValidationException::withMessages([
                        'supervisor_record_id' => ['Supervisor already has an account.'],
                    ]);
                }
            }

            $user = User::create([
                'name' => $supervisor?->full_name ?? $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword),
                'role' => $data['role'],
                'is_active' => $data['is_active'] ?? true,
                'requires_password_change' => UserId::isStaffRole($data['role']),
                'requires_security_setup' => UserId::isStaffRole($data['role']),
                'requires_profile_setup' => UserId::isStaffRole($data['role']),
                'profile_completed_at' => UserId::isStaffRole($data['role']) ? null : now(),
                ...$this->assignmentAttributes($data),
            ]);

            $supervisor?->update(['user_id' => $user->id]);

            return $user;
        });

        return response()->json([
            'message' => $data['role'] === 'supervisor'
                ? 'Supervisor account created and linked to the existing assignment.'
                : (UserId::isStaffRole($data['role'])
                    ? 'Staff account created. The profile is pending first-login setup.'
                    : 'User account created successfully.'),
            'user' => $user->load(['college:id,name,code', 'program:id,college_id,name,code', 'supervisorProfile.department.hte:id,name,address']),
            'temporary_password' => $temporaryPassword,
        ], 201);
    }

    public function supervisorAssignments(Request $request)
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $term = trim($validated['search']);

        $supervisors = Supervisor::query()
            ->with(['department.hte:id,name,address', 'supervisorProfile', 'user:id,email,username,is_active'])
            ->where(function ($query) use ($term) {
                $query->whereHas('supervisorProfile', fn ($profile) => $profile->where('full_name', 'like', '%'.$term.'%'))
                    ->orWhereHas('department', fn ($departmentQuery) => $departmentQuery
                        ->where('department_name', 'like', '%'.$term.'%')
                        ->orWhereHas('hte', fn ($hteQuery) => $hteQuery->where('name', 'like', '%'.$term.'%')));
            })
            ->orderBy('id')
            ->limit(30)
            ->get()
            ->map(fn (Supervisor $supervisor) => [
                'id' => $supervisor->id,
                'full_name' => $supervisor->full_name,
                'contact_email' => $supervisor->contact_email,
                'department' => $supervisor->department,
                'hte' => $supervisor->department?->hte,
                'has_account' => (bool) $supervisor->user_id,
                'account' => $supervisor->user,
            ]);

        return response()->json(['supervisors' => $supervisors]);
    }

    public function show(User $user)
    {
        return response()->json($user->load(['college:id,name,code', 'program:id,college_id,name,code', 'supervisorProfile.department.hte:id,name,address']));
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
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => 'sometimes|in:student,coordinator,program_head,dean,vpaa,admin,supervisor',
            'supervisor_record_id' => ['nullable', 'integer', 'exists:hte_supervisor_assignments,id'],
            ...$this->assignmentRules($request, $user),
        ]);
        $data['role'] = $data['role'] ?? $user->role;
        $this->validateAssignment($data);

        DB::transaction(function () use ($data, $user): void {
            $supervisor = $this->syncSupervisorAssignment($user, $data);
            $user->update([
                'name' => $supervisor?->full_name ?? $data['name'],
                'email' => $data['email'] ?? $user->email,
                'role' => $data['role'],
                ...$this->assignmentAttributes($data),
            ]);
        });

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

    public function permanentDestroy(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your current account.');

        $hasHistoricalRecords = $user->student()->exists()
            || $user->supervisedStudents()->exists()
            || $user->activityLogs()->exists()
            || $user->uploadedReportTemplates()->exists()
            || $user->generatedReports()->exists();

        abort_if(
            $hasHistoricalRecords,
            422,
            'This account has connected historical records and cannot be permanently deleted. Deactivate it instead.',
        );

        try {
            DB::transaction(function () use ($user): void {
                $user->tokens()->delete();
                $user->delete();
            });
        } catch (QueryException) {
            abort(422, 'This account is referenced by another record and cannot be permanently deleted. Deactivate it instead.');
        }

        return response()->json(['message' => 'User account permanently deleted.']);
    }

    public function updateRole(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'You cannot change the role of your current account.');
        $data = $request->validate([
            'role' => 'required|in:student,coordinator,program_head,dean,vpaa,admin,supervisor',
            'supervisor_record_id' => ['nullable', 'integer', 'exists:hte_supervisor_assignments,id'],
            ...$this->assignmentRules($request, $user),
        ]);
        $this->validateAssignment($data);

        DB::transaction(function () use ($data, $user): void {
            $supervisor = $this->syncSupervisorAssignment($user, $data);
            $user->update([
                'name' => $supervisor?->full_name ?? $user->name,
                'role' => $data['role'],
                ...$this->assignmentAttributes($data),
            ]);
        });

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
            'password' => SystemSetting::passwordRules(),
        ]);

        $user->update([
            'password' => Hash::make($request->password),
            'requires_password_change' => UserId::isStaffRole($user->role),
        ]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset successfully']);
    }

    private function assignmentRules(Request $request, ?User $user = null): array
    {
        $role = $request->input('role', $user?->role);
        $scoped = in_array($role, ['coordinator', 'program_head'], true);
        $collegeScoped = $scoped || $role === 'dean';

        return [
            'college_id' => [$collegeScoped ? 'required' : 'nullable', 'integer', Rule::exists('colleges', 'id')],
            'program_id' => [$scoped ? 'required' : 'nullable', 'integer', Rule::exists('programs', 'id')],
        ];
    }

    private function generateTemporaryPassword(): string
    {
        $length = SystemSetting::minimumPasswordLength();
        do {
            $password = Str::password($length, true, true, true, false);
        } while (
            ! preg_match('/[A-Z]/', $password)
            || ! preg_match('/[a-z]/', $password)
            || ! preg_match('/\d/', $password)
            || ! preg_match('/[^A-Za-z0-9]/', $password)
        );

        return $password;
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
        if ($data['role'] === 'dean') {
            return ['college_id' => $data['college_id'], 'program_id' => null];
        }
        if (! in_array($data['role'], ['coordinator', 'program_head'], true)) {
            return ['college_id' => null, 'program_id' => null];
        }

        return [
            'college_id' => $data['college_id'],
            'program_id' => $data['program_id'],
        ];
    }

    private function syncSupervisorAssignment(User $user, array $data): ?Supervisor
    {
        $current = $user->supervisorProfile()->lockForUpdate()->first();

        if ($data['role'] !== 'supervisor') {
            $current?->update(['user_id' => null]);

            return null;
        }

        $supervisorId = $data['supervisor_record_id'] ?? $current?->id;
        if (! $supervisorId) {
            throw ValidationException::withMessages([
                'supervisor_record_id' => ['Select an existing Supervisor assignment.'],
            ]);
        }

        $supervisor = Supervisor::lockForUpdate()->findOrFail($supervisorId);
        if ($supervisor->user_id && (int) $supervisor->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'supervisor_record_id' => ['Supervisor already has an account.'],
            ]);
        }

        if ($current && ! $current->is($supervisor)) {
            $current->update(['user_id' => null]);
        }
        $supervisor->update(['user_id' => $user->id]);

        return $supervisor;
    }
}
