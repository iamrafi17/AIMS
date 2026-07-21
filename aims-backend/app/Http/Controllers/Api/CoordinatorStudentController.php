<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorStudentController extends Controller
{
    public function index(Request $request)
    {
        $students = Student::with(['user', 'college', 'program', 'hte', 'attendance', 'requirements'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('student_id', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->status, fn ($query, $status) => $query->where('registration_status', $status))
            ->when($request->internship_status, fn ($query, $status) => $query->where('internship_status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12);

        $students->getCollection()->transform(fn (Student $student) => $this->studentPayload($student));

        return response()->json([
            ...$students->toArray(),
            'summary' => [
                'total' => Student::count(),
                'pending' => Student::where('registration_status', 'pending')->count(),
                'approved' => Student::where('registration_status', 'approved')->count(),
                'rejected' => Student::where('registration_status', 'rejected')->count(),
                'active' => Student::where('internship_status', 'active')->count(),
            ],
        ]);
    }

    public function options()
    {
        return response()->json([
            'colleges' => College::with(['programs' => fn ($query) => $query->where('is_active', true)])
                ->where('is_active', true)->orderBy('name')->get(),
            'htes' => HTE::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->studentRules());
        $this->validateProgramCollege($validated);
        $temporaryPassword = $validated['password'] ?? Str::password(12);

        $student = DB::transaction(function () use ($validated, $temporaryPassword) {
            $user = User::create([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'role' => 'student',
                'is_active' => true,
            ]);

            return Student::create($this->studentAttributes($validated, $user->id));
        });

        return response()->json([
            'message' => 'Student record and login account created successfully.',
            'student' => $this->studentPayload($student->load(['user', 'college', 'program', 'hte', 'attendance', 'requirements'])),
            'temporary_password' => array_key_exists('password', $validated) ? null : $temporaryPassword,
        ], 201);
    }

    public function show(Student $student)
    {
        return response()->json($this->studentPayload($student->load(['user', 'college', 'program', 'hte', 'attendance', 'requirements.reviewer'])));
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate($this->studentRules($student));
        $this->validateProgramCollege($validated);

        DB::transaction(function () use ($validated, $student) {
            $student->user->update([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => $validated['email'],
                ...(! empty($validated['password']) ? ['password' => Hash::make($validated['password'])] : []),
            ]);
            $student->update($this->studentAttributes($validated, $student->user_id));
        });

        return response()->json([
            'message' => 'Student record updated successfully.',
            'student' => $this->studentPayload($student->fresh()->load(['user', 'college', 'program', 'hte', 'attendance', 'requirements'])),
        ]);
    }

    public function destroy(Student $student)
    {
        $paths = $student->requirements()->pluck('file_path')->filter();
        $avatar = $student->user?->avatar;
        $user = $student->user;

        DB::transaction(fn () => $user ? $user->delete() : $student->delete());
        $paths->each(fn ($path) => Storage::disk('public')->delete($path));
        if ($avatar) {
            Storage::disk('public')->delete($avatar);
        }

        return response()->json(['message' => 'Student record and login account deleted successfully.']);
    }

    public function approveRegistration(Student $student, Request $request)
    {
        $student->update([
            'registration_status' => 'approved',
            'internship_status' => $student->internship_status === 'pending' ? 'active' : $student->internship_status,
            'registration_feedback' => $request->input('feedback'),
            'registration_reviewed_by' => $request->user()->id,
            'registration_reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Student registration approved.']);
    }

    public function rejectRegistration(Student $student, Request $request)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $student->update([
            'registration_status' => 'rejected',
            'registration_feedback' => $validated['reason'],
            'registration_reviewed_by' => $request->user()->id,
            'registration_reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Student registration rejected.']);
    }

    public function reviewRequirement(Request $request, Student $student, InternshipRequirement $requirement)
    {
        if ($requirement->student_id !== $student->id) {
            return response()->json(['message' => 'Requirement does not belong to this student.'], 404);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'feedback' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);

        $requirement->update([
            'status' => $validated['decision'],
            'feedback' => $validated['feedback'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Requirement '.$validated['decision'].'.',
            'requirement' => $requirement->fresh('reviewer'),
        ]);
    }

    public function downloadRequirement(Student $student, InternshipRequirement $requirement)
    {
        if ($requirement->student_id !== $student->id || ! $requirement->file_path || ! Storage::disk('public')->exists($requirement->file_path)) {
            return response()->json(['message' => 'Requirement file not found.'], 404);
        }

        return Storage::disk('public')->download($requirement->file_path);
    }

    public function importCsv(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $headers = array_map(fn ($header) => Str::snake(trim((string) $header)), fgetcsv($handle) ?: []);
        $requiredHeaders = ['student_id', 'first_name', 'last_name', 'email', 'college_code', 'program_code', 'year_level', 'section'];

        if (array_diff($requiredHeaders, $headers)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'CSV is missing required headers: '.implode(', ', array_diff($requiredHeaders, $headers))]);
        }

        $imported = 0;
        $errors = [];
        $credentials = [];
        $rowNumber = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count($headers), null);
            $row = array_combine($headers, array_slice($values, 0, count($headers)));

            try {
                $college = College::where('code', trim($row['college_code']))->firstOrFail();
                $program = Program::where('college_id', $college->id)->where('code', trim($row['program_code']))->firstOrFail();
                $hte = filled($row['hte_name'] ?? null) ? HTE::where('name', trim($row['hte_name']))->first() : null;
                $password = filled($row['password'] ?? null) ? trim($row['password']) : Str::password(12);
                $payload = [
                    'student_id' => trim($row['student_id']), 'first_name' => trim($row['first_name']), 'last_name' => trim($row['last_name']),
                    'middle_name' => trim($row['middle_name'] ?? '') ?: null, 'email' => trim($row['email']), 'password' => $password,
                    'gender' => in_array(strtolower(trim($row['gender'] ?? 'other')), ['male', 'female', 'other'], true) ? strtolower(trim($row['gender'] ?? 'other')) : 'other',
                    'birth_date' => trim($row['birth_date'] ?? '') ?: '2000-01-01', 'address' => trim($row['address'] ?? '') ?: 'Not provided',
                    'phone' => trim($row['phone'] ?? '') ?: 'Not provided', 'college_id' => $college->id, 'program_id' => $program->id,
                    'year_level' => (int) $row['year_level'], 'section' => trim($row['section']), 'parent_name' => trim($row['parent_name'] ?? '') ?: 'Not provided',
                    'parent_relationship' => trim($row['parent_relationship'] ?? '') ?: null, 'parent_address' => trim($row['parent_address'] ?? '') ?: 'Not provided',
                    'parent_phone' => trim($row['parent_phone'] ?? '') ?: 'Not provided', 'hte_id' => $hte?->id,
                    'internship_semester' => trim($row['internship_semester'] ?? '') ?: null, 'internship_year' => trim($row['internship_year'] ?? '') ?: null,
                    'registration_status' => in_array(trim($row['registration_status'] ?? ''), ['pending', 'approved', 'rejected'], true) ? trim($row['registration_status']) : 'pending',
                    'internship_status' => in_array(trim($row['internship_status'] ?? ''), ['pending', 'active', 'completed', 'dropped'], true) ? trim($row['internship_status']) : 'pending',
                ];

                validator($payload, $this->studentRules())->validate();
                DB::transaction(function () use ($payload) {
                    $user = User::create(['name' => trim($payload['first_name'].' '.$payload['last_name']), 'email' => $payload['email'], 'password' => Hash::make($payload['password']), 'role' => 'student']);
                    Student::create($this->studentAttributes($payload, $user->id));
                });
                $imported++;
                $credentials[] = ['student_id' => $payload['student_id'], 'email' => $payload['email'], 'temporary_password' => $password];
            } catch (\Throwable $exception) {
                $errors[] = ['row' => $rowNumber, 'student_id' => $row['student_id'] ?? null, 'message' => $exception instanceof ValidationException ? collect($exception->errors())->flatten()->first() : $exception->getMessage()];
            }
        }
        fclose($handle);

        return response()->json([
            'message' => $imported.' student record(s) imported; '.count($errors).' row(s) failed.',
            'imported' => $imported,
            'failed' => count($errors),
            'errors' => $errors,
            'credentials' => $credentials,
        ]);
    }

    private function studentRules(?Student $student = null): array
    {
        return [
            'student_id' => ['required', 'string', 'max:20', Rule::unique('students')->ignore($student?->id)],
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($student?->user_id)], 'password' => [$student ? 'nullable' : 'nullable', 'string', 'min:8'],
            'gender' => ['required', 'in:male,female,other'], 'birth_date' => ['required', 'date', 'before:today'], 'address' => ['required', 'string', 'max:1000'], 'phone' => ['required', 'string', 'max:20'],
            'college_id' => ['required', 'exists:colleges,id'], 'program_id' => ['required', 'exists:programs,id'], 'year_level' => ['required', 'integer', 'between:1,5'], 'section' => ['required', 'string', 'max:50'],
            'parent_name' => ['required', 'string', 'max:255'], 'parent_relationship' => ['nullable', 'string', 'max:50'], 'parent_address' => ['required', 'string', 'max:1000'], 'parent_phone' => ['required', 'string', 'max:20'],
            'hte_id' => ['nullable', 'exists:htes,id'], 'internship_semester' => ['nullable', 'string', 'max:30'], 'internship_year' => ['nullable', 'string', 'max:20'],
            'registration_status' => ['nullable', 'in:pending,approved,rejected'], 'internship_status' => ['nullable', 'in:pending,active,completed,dropped'],
            'consent_status' => ['nullable', 'in:pending,rejected,done'], 'schedule_status' => ['nullable', 'in:pending,rejected,approved'],
            'ojt_start_date' => ['nullable', 'date'], 'ojt_end_date' => ['nullable', 'date', 'after_or_equal:ojt_start_date'], 'required_ojt_hours' => ['nullable', 'numeric', 'between:1,2000'], 'allow_past_attendance' => ['nullable', 'boolean'],
        ];
    }

    private function validateProgramCollege(array $data): void
    {
        if (! Program::whereKey($data['program_id'])->where('college_id', $data['college_id'])->exists()) {
            throw ValidationException::withMessages(['program_id' => 'The selected program does not belong to the selected college.']);
        }
    }

    private function studentAttributes(array $data, int $userId): array
    {
        return [
            'user_id' => $userId, 'student_id' => $data['student_id'], 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'middle_name' => $data['middle_name'] ?? null,
            'gender' => $data['gender'], 'birth_date' => $data['birth_date'], 'address' => $data['address'], 'phone' => $data['phone'], 'college_id' => $data['college_id'], 'program_id' => $data['program_id'],
            'year_level' => $data['year_level'], 'section' => $data['section'], 'parent_name' => $data['parent_name'], 'parent_relationship' => $data['parent_relationship'] ?? null,
            'parent_address' => $data['parent_address'], 'parent_phone' => $data['parent_phone'], 'hte_id' => $data['hte_id'] ?? null,
            'internship_semester' => $data['internship_semester'] ?? null, 'internship_year' => $data['internship_year'] ?? null,
            'registration_status' => $data['registration_status'] ?? 'pending', 'internship_status' => $data['internship_status'] ?? 'pending',
            'consent_status' => $data['consent_status'] ?? 'pending', 'schedule_status' => $data['schedule_status'] ?? 'pending',
            'ojt_start_date' => $data['ojt_start_date'] ?? null, 'ojt_end_date' => $data['ojt_end_date'] ?? null, 'required_ojt_hours' => $data['required_ojt_hours'] ?? 486,
            'allow_past_attendance' => $data['allow_past_attendance'] ?? false,
        ];
    }

    private function studentPayload(Student $student): array
    {
        $hours = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));
        $required = max((float) $student->required_ojt_hours, 0);
        $approvedRequirements = $student->requirements->where('status', 'approved')->count();
        $requirementTotal = $student->requirements->count();

        return [
            ...$student->toArray(),
            'progress' => [
                'rendered_hours' => round($hours, 1), 'required_hours' => round($required, 1), 'percent' => $required > 0 ? round(min(($hours / $required) * 100, 100), 1) : 0,
                'attendance_days' => $student->attendance->whereIn('status', ['present', 'late'])->count(),
                'journal_entries' => $student->attendance->sum(fn (Attendance $record) => (blank($record->am_activity) ? 0 : 1) + (blank($record->pm_activity) ? 0 : 1)),
                'requirements_approved' => $approvedRequirements, 'requirements_total' => $requirementTotal,
                'requirements_percent' => $requirementTotal > 0 ? round(($approvedRequirements / $requirementTotal) * 100, 1) : 0,
            ],
        ];
    }

    private function renderedHours(Attendance $record): float
    {
        return collect([[$record->am_time_in, $record->am_time_out], [$record->pm_time_in, $record->pm_time_out]])
            ->sum(fn ($slot) => $slot[0] && $slot[1] ? $slot[0]->diffInSeconds($slot[1]) / 3600 : 0) + (float) $record->overtime_hours;
    }
}
