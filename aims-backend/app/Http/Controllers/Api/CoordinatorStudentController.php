<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\OjtEnrollment;
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
        $students = Student::with(['user', 'college', 'program', 'hte', 'attendance'])
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

        InternshipRequirement::ensureForStudents($students->getCollection()->pluck('id'));
        $students->getCollection()->load('requirements');
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

    public function enrollments(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        return response()->json([
            'data' => OjtEnrollment::with(['student.user'])
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($nested) use ($search) {
                        $nested->where('school_id', 'like', '%'.$search.'%')
                            ->orWhere('full_name', 'like', '%'.$search.'%')
                            ->orWhere('section', 'like', '%'.$search.'%');
                    });
                })
                ->latest()
                ->limit(500)
                ->get(),
            'summary' => [
                'total' => OjtEnrollment::count(),
                'awaiting_registration' => OjtEnrollment::whereNull('registered_at')->count(),
                'registered' => OjtEnrollment::whereNotNull('registered_at')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'school_id' => ['required', 'string', 'max:20', Rule::unique('ojt_enrollments', 'school_id'), Rule::unique('students', 'student_id')],
            'section' => ['required', 'string', 'max:50'],
        ]);

        $enrollment = OjtEnrollment::create([
            'school_id' => trim($validated['school_id']),
            'full_name' => preg_replace('/\s+/', ' ', trim($validated['full_name'])),
            'section' => trim($validated['section']),
            'source' => 'manual',
            'added_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Student added to the OJT enrollment list. They can now register their account.',
            'enrollment' => $enrollment,
        ], 201);
    }

    public function show(Student $student)
    {
        InternshipRequirement::ensureForStudent($student->id);

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

        if (! $requirement->file_path) {
            return response()->json(['message' => 'The student has not uploaded this requirement yet.'], 422);
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
        $schoolIdHeader = in_array('school_id', $headers, true) ? 'school_id' : 'student_id';
        $fullNameHeader = in_array('full_name', $headers, true) ? 'full_name' : 'student_name';
        $requiredHeaders = [$schoolIdHeader, $fullNameHeader, 'section'];

        if (array_diff($requiredHeaders, $headers)) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'CSV must contain school_id, full_name, and section columns.',
            ]);
        }

        $imported = 0;
        $errors = [];
        $rowNumber = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count($headers), null);
            $row = array_combine($headers, array_slice($values, 0, count($headers)));

            try {
                $payload = validator([
                    'school_id' => trim((string) ($row[$schoolIdHeader] ?? '')),
                    'full_name' => trim((string) ($row[$fullNameHeader] ?? '')),
                    'section' => trim((string) ($row['section'] ?? '')),
                ], [
                    'school_id' => ['required', 'string', 'max:20', Rule::unique('ojt_enrollments', 'school_id'), Rule::unique('students', 'student_id')],
                    'full_name' => ['required', 'string', 'max:255'],
                    'section' => ['required', 'string', 'max:50'],
                ])->validate();

                OjtEnrollment::create([
                    'school_id' => $payload['school_id'],
                    'full_name' => preg_replace('/\s+/', ' ', trim($payload['full_name'])),
                    'section' => $payload['section'],
                    'source' => 'csv',
                    'added_by' => $request->user()->id,
                ]);
                $imported++;
            } catch (\Throwable $exception) {
                $errors[] = ['row' => $rowNumber, 'school_id' => $row[$schoolIdHeader] ?? null, 'message' => $exception instanceof ValidationException ? collect($exception->errors())->flatten()->first() : $exception->getMessage()];
            }
        }
        fclose($handle);

        return response()->json([
            'message' => $imported.' student(s) added to the OJT enrollment list; '.count($errors).' row(s) failed.',
            'imported' => $imported,
            'failed' => count($errors),
            'errors' => $errors,
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
        $requirementOrder = array_flip(InternshipRequirement::OFFICIAL_REQUIREMENTS);
        $officialRequirements = $student->requirements
            ->whereIn('requirement_name', InternshipRequirement::OFFICIAL_REQUIREMENTS)
            ->sortBy(fn (InternshipRequirement $requirement) => $requirementOrder[$requirement->requirement_name])
            ->values();
        $approvedRequirements = $officialRequirements->where('status', 'approved')->count();
        $requirementTotal = $officialRequirements->count();
        $studentData = $student->toArray();
        $studentData['requirements'] = $officialRequirements->toArray();

        return [
            ...$studentData,
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
