<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\SystemNotification;
use App\Models\User;
use App\Support\AttendanceHours;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorStudentController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'academic_section_id' => ['nullable', 'integer', ...$this->sectionExistsRule($programId)],
        ]);
        $sectionId = isset($filters['academic_section_id']) ? (int) $filters['academic_section_id'] : null;
        $students = Student::with(['user', 'college', 'program', 'hte', 'attendance'])
            ->forProgram($programId)
            ->when($sectionId, fn ($query) => $query->forAcademicSection($sectionId))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('student_id', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->internship_status, fn ($query, $status) => $query->forInternshipStatus($status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        InternshipRequirement::ensureForStudents($students->getCollection()->pluck('id'));
        $students->getCollection()->load('requirements');
        $students->getCollection()->transform(fn (Student $student) => $this->studentPayload($student));

        return response()->json([
            ...$students->toArray(),
            'summary' => [
                'total' => $this->studentScope($programId, $sectionId)->count(),
                'awaiting_deployment' => $this->studentScope($programId, $sectionId)->forInternshipStatus('pending')->count(),
                'completed' => $this->studentScope($programId, $sectionId)->forInternshipStatus('completed')->count(),
                'dropped' => $this->studentScope($programId, $sectionId)->forInternshipStatus('dropped')->count(),
                'active' => $this->studentScope($programId, $sectionId)->forInternshipStatus('active')->count(),
            ],
        ]);
    }

    public function options(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $collegeId = (int) $request->user()->college_id;

        return response()->json([
            'colleges' => College::with(['programs' => fn ($query) => $query->whereKey($programId)->where('is_active', true)])
                ->whereKey($collegeId)->where('is_active', true)->get(),
            'htes' => HTE::where('is_active', true)
                ->whereHas('programs', fn ($query) => $query->whereKey($programId)->where('hte_programs.is_active', true))
                ->orderBy('name')->get(['id', 'name']),
            'sections' => AcademicSection::where('program_id', $programId)
                ->where('is_active', true)
                ->newestPeriodFirst()
                ->orderBy('year_level')
                ->orderBy('name')
                ->get(['id', 'program_id', 'academic_period_id', 'name', 'year_level']),
        ]);
    }

    public function enrollments(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'academic_section_id' => ['nullable', 'integer', ...$this->sectionExistsRule($programId)],
        ]);
        $sectionId = isset($filters['academic_section_id']) ? (int) $filters['academic_section_id'] : null;
        $search = trim((string) ($filters['search'] ?? ''));
        $enrollments = OjtEnrollment::with([
            'student.user',
            'academicSection.program.college',
            'academicSection.academicPeriod',
        ])
            ->forProgram($programId)
            ->when($sectionId, fn ($query) => $query->where('academic_section_id', $sectionId))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('school_id', 'like', '%'.$search.'%')
                        ->orWhere('full_name', 'like', '%'.$search.'%')
                        ->orWhereHas('academicSection', fn ($section) => $section->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return response()->json([
            ...$enrollments->toArray(),
            'summary' => [
                'total' => $this->enrollmentScope($programId, $sectionId)->count(),
                'awaiting_registration' => $this->enrollmentScope($programId, $sectionId)->whereNull('registered_at')->count(),
                'registered' => $this->enrollmentScope($programId, $sectionId)->whereNotNull('registered_at')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'school_id' => ['required', 'string', 'max:20', Rule::unique('ojt_enrollments', 'school_id'), Rule::unique('students', 'student_id')],
            'academic_section_id' => ['required', 'integer', ...$this->sectionExistsRule($programId)],
        ]);
        $section = AcademicSection::findOrFail($validated['academic_section_id']);

        $enrollment = OjtEnrollment::create([
            'school_id' => trim($validated['school_id']),
            'full_name' => preg_replace('/\s+/', ' ', trim($validated['full_name'])),
            'academic_section_id' => $section->id,
            'source' => 'manual',
            'added_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Student added to the OJT enrollment list. They can now register their account.',
            'enrollment' => $enrollment,
        ], 201);
    }

    public function show(Request $request, Student $student)
    {
        ProgramAccess::authorizeStudent($request->user(), $student);
        InternshipRequirement::ensureForStudent($student->id);

        return response()->json($this->studentPayload($student->load(['user', 'college', 'program', 'hte', 'attendance', 'requirements.reviewer'])));
    }

    public function update(Request $request, Student $student)
    {
        ProgramAccess::authorizeStudent($request->user(), $student);
        $validated = $request->validate($this->studentRules($student, $request->user()));

        DB::transaction(function () use ($validated, $student, $request) {
            $student->user->update([
                'name' => collect([
                    $validated['first_name'],
                    $validated['middle_name'] ?? null,
                    $validated['last_name'],
                ])->filter()->implode(' '),
                'email' => $validated['email'],
                'contact_number' => $validated['contact_number'],
                'address' => $validated['address'],
                ...(! empty($validated['password']) ? ['password' => Hash::make($validated['password'])] : []),
            ]);
            $student->update($this->studentAttributes($validated, $student->user_id, $request->user()));
        });

        return response()->json([
            'message' => 'Student record updated successfully.',
            'student' => $this->studentPayload($student->fresh()->load(['user', 'college', 'program', 'hte', 'attendance', 'requirements'])),
        ]);
    }

    public function destroy(Request $request, Student $student)
    {
        ProgramAccess::authorizeStudent($request->user(), $student);
        DB::transaction(function () use ($student) {
            $user = $student->user;

            foreach ($student->travelLogs()->get() as $travelLog) {
                $travelLog->delete();
            }

            OjtEnrollment::where('student_record_id', $student->id)->delete();
            $student->delete();

            if ($user) {
                $user->tokens()->delete();
                $user->delete();
            }
        });

        return response()->json(['message' => 'Student record deleted successfully.']);
    }

    public function reviewRequirement(Request $request, Student $student, InternshipRequirement $requirement)
    {
        ProgramAccess::authorizeStudent($request->user(), $student);
        ProgramAccess::authorizeRequirement($request->user(), $requirement);
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
        SystemNotification::sendToUser($student->user_id, 'Requirement '.$validated['decision'], $requirement->requirement_name.' was '.$validated['decision'].'.'.(! empty($validated['feedback']) ? ' '.$validated['feedback'] : ''), 'requirement', '/student/requirements');

        return response()->json([
            'message' => 'Requirement '.$validated['decision'].'.',
            'requirement' => $requirement->fresh('reviewer'),
        ]);
    }

    public function downloadRequirement(Request $request, Student $student, InternshipRequirement $requirement)
    {
        ProgramAccess::authorizeStudent($request->user(), $student);
        ProgramAccess::authorizeRequirement($request->user(), $requirement);
        $path = $this->privateStoragePath($requirement->file_path);
        if ($requirement->student_id !== $student->id || ! $path || ! $this->privateDisk()->exists($path)) {
            return response()->json(['message' => 'Requirement file not found.'], 404);
        }

        return $this->privateDisk()->download($path);
    }

    public function importCsv(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv', 'max:5120'],
            'academic_section_id' => ['required', 'integer', ...$this->sectionExistsRule($programId)],
        ]);
        $section = AcademicSection::findOrFail($validated['academic_section_id']);
        $students = $this->analyzePredefinedCsv($request->file('file'));

        return response()->json([
            'message' => 'Student list is ready for review.',
            'file_name' => $request->file('file')->getClientOriginalName(),
            'section' => $this->sectionPayload($section),
            'students' => $students,
            'summary' => $this->importSummary($students),
        ]);
    }

    public function confirmCsvImport(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv', 'max:5120'],
            'academic_section_id' => ['required', 'integer', ...$this->sectionExistsRule($programId)],
        ]);
        $section = AcademicSection::findOrFail($validated['academic_section_id']);
        $students = $this->analyzePredefinedCsv($request->file('file'));
        $imported = 0;

        DB::transaction(function () use ($request, $students, $section, &$imported) {
            foreach ($students as $student) {
                if ($student['status'] !== 'Ready') {
                    continue;
                }

                $alreadyExists = OjtEnrollment::where('school_id', $student['school_no'])->exists()
                    || Student::where('student_id', $student['school_no'])->exists();

                if ($alreadyExists) {
                    continue;
                }

                OjtEnrollment::create([
                    'school_id' => $student['school_no'],
                    'full_name' => $student['full_name'],
                    'academic_section_id' => $section->id,
                    'source' => 'csv',
                    'added_by' => $request->user()->id,
                ]);
                $imported++;
            }
        });

        return response()->json([
            'message' => 'Student list imported successfully.',
            'detail' => $imported.' students were successfully imported.',
            'section' => $this->sectionPayload($section),
            'imported' => $imported,
            'skipped' => count($students) - $imported,
        ]);
    }

    private function analyzePredefinedCsv(UploadedFile $file): array
    {
        $rows = $this->readPredefinedStudentRows($file);
        $schoolNumbers = collect($rows)
            ->pluck('school_no')
            ->filter()
            ->map(fn ($schoolNo) => Str::upper($schoolNo));
        $counts = $schoolNumbers->countBy();
        $uniqueSchoolNumbers = $schoolNumbers->unique()->values();
        $existingEnrollments = OjtEnrollment::whereIn('school_id', $uniqueSchoolNumbers)
            ->pluck('school_id')
            ->mapWithKeys(fn ($schoolNo) => [Str::upper($schoolNo) => true]);
        $existingStudents = Student::whereIn('student_id', $uniqueSchoolNumbers)
            ->pluck('student_id')
            ->mapWithKeys(fn ($schoolNo) => [Str::upper($schoolNo) => true]);

        return array_map(function (array $student) use ($counts, $existingEnrollments, $existingStudents) {
            $schoolNo = $student['school_no'];
            $fullName = $student['full_name'];
            $key = Str::upper($schoolNo);
            $status = 'Ready';
            $reason = null;

            if ($schoolNo === '' || $fullName === '') {
                $status = 'Invalid';
                $reason = $schoolNo === ''
                    ? 'School No. is required.'
                    : 'Full Name is required.';
            } elseif (mb_strlen($schoolNo) > 20 || mb_strlen($fullName) > 255) {
                $status = 'Invalid';
                $reason = mb_strlen($schoolNo) > 20
                    ? 'School No. must not exceed 20 characters.'
                    : 'Full Name must not exceed 255 characters.';
            } elseif (($counts[$key] ?? 0) > 1) {
                $status = 'Duplicate';
                $reason = 'School No. appears more than once in this file.';
            } elseif ($existingEnrollments->has($key) || $existingStudents->has($key)) {
                $status = 'Duplicate';
                $reason = 'School No. already exists in AIMS.';
            }

            return [
                ...$student,
                'status' => $status,
                'reason' => $reason,
            ];
        }, $rows);
    }

    private function readPredefinedStudentRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            $this->invalidStudentListTemplate();
        }

        $hasOfficialTitle = false;
        $hasStudentNumberHeader = false;
        $hasFullNameHeader = false;
        $students = [];
        $rowNumber = 0;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $cells = array_map(
                    fn ($value) => trim(ltrim((string) $value, "\xEF\xBB\xBF")),
                    $row,
                );

                foreach ($cells as $cell) {
                    $hasOfficialTitle = $hasOfficialTitle
                        || Str::contains(Str::upper($cell), 'OFFICIAL LIST OF ENROLLED STUDENTS');
                    $hasStudentNumberHeader = $hasStudentNumberHeader
                        || preg_match('/^(STUDENT|SCHOOL)\s+NO\.?$/i', $cell) === 1;
                    $hasFullNameHeader = $hasFullNameHeader
                        || strcasecmp($cell, 'Full Name') === 0;
                }

                if (preg_match('/^\d+\.?$/', trim((string) ($row[2] ?? ''))) !== 1) {
                    continue;
                }

                $students[] = [
                    'row' => $rowNumber,
                    'school_no' => trim((string) ($row[4] ?? '')),
                    'full_name' => preg_replace('/\s+/', ' ', trim((string) ($row[5] ?? ''))),
                ];
            }
        } finally {
            fclose($handle);
        }

        if (! $hasOfficialTitle || ! $hasStudentNumberHeader || ! $hasFullNameHeader || $students === []) {
            $this->invalidStudentListTemplate();
        }

        return $students;
    }

    private function importSummary(array $students): array
    {
        $statuses = collect($students)->countBy('status');

        return [
            'students_found' => count($students),
            'ready' => $statuses->get('Ready', 0),
            'duplicates' => $statuses->get('Duplicate', 0),
            'invalid' => $statuses->get('Invalid', 0),
        ];
    }

    private function invalidStudentListTemplate(): never
    {
        throw ValidationException::withMessages([
            'file' => ['The uploaded file does not match the required student list template.'],
        ]);
    }

    private function sectionExistsRule(int $programId): array
    {
        return [Rule::exists('academic_sections', 'id')->where(fn ($query) => $query
            ->where('program_id', $programId)
            ->where('is_active', true))];
    }

    private function sectionPayload(AcademicSection $section): array
    {
        return [
            'id' => $section->id,
            'name' => $section->name,
            'year_level' => $section->year_level,
            'academic_year' => $section->academic_year,
        ];
    }

    private function studentScope(int $programId, ?int $sectionId)
    {
        return Student::query()
            ->forProgram($programId)
            ->when($sectionId, fn ($query) => $query->forAcademicSection($sectionId));
    }

    private function enrollmentScope(int $programId, ?int $sectionId)
    {
        return OjtEnrollment::query()
            ->forProgram($programId)
            ->when($sectionId, fn ($query) => $query->where('academic_section_id', $sectionId));
    }

    private function studentRules(?Student $student, User $coordinator): array
    {
        return [
            'student_id' => ['required', 'string', 'max:20', Rule::unique('students')->ignore($student?->id)],
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($student?->user_id)], 'password' => [$student ? 'nullable' : 'nullable', 'string', 'min:8', 'max:255'],
            'gender' => ['required', 'in:male,female'], 'birth_date' => ['required', 'date', 'before:today'], 'address' => ['required', 'string', 'max:1000'], 'contact_number' => ['required', 'string', 'digits:11'],
            'college_id' => ['nullable', Rule::in([(int) $coordinator->college_id])], 'program_id' => ['nullable', Rule::in([(int) $coordinator->program_id])], 'year_level' => ['required', 'integer', 'between:1,5'], 'section' => ['required', 'string', 'max:50'],
            'parent_name' => ['required', 'string', 'max:255'], 'parent_relationship' => ['nullable', 'string', 'max:50'], 'parent_address' => ['required', 'string', 'max:1000'], 'parent_contact_number' => ['required', 'string', 'digits:11'],
            'hte_id' => ['nullable', Rule::exists('hte_programs', 'hte_id')->where(fn ($query) => $query->where('program_id', $coordinator->program_id)->where('is_active', true))], 'internship_semester' => ['nullable', 'string', 'max:30'], 'internship_year' => ['nullable', 'string', 'max:20'],
            'internship_status' => ['nullable', 'in:pending,active,completed,dropped'],
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

    private function studentAttributes(array $data, int $userId, User $coordinator): array
    {
        return [
            'user_id' => $userId, 'student_id' => $data['student_id'], 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'middle_name' => $data['middle_name'] ?? null,
            'gender' => $data['gender'], 'birth_date' => $data['birth_date'], 'address' => $data['address'], 'contact_number' => $data['contact_number'], 'college_id' => $coordinator->college_id, 'program_id' => $coordinator->program_id,
            'year_level' => $data['year_level'], 'section' => $data['section'], 'parent_name' => $data['parent_name'], 'parent_relationship' => $data['parent_relationship'] ?? null,
            'parent_address' => $data['parent_address'], 'parent_contact_number' => $data['parent_contact_number'], 'hte_id' => $data['hte_id'] ?? null,
            'internship_semester' => $data['internship_semester'] ?? null, 'internship_year' => $data['internship_year'] ?? null,
            'internship_status' => $data['internship_status'] ?? 'pending',
            'consent_status' => $data['consent_status'] ?? 'pending', 'schedule_status' => $data['schedule_status'] ?? 'pending',
            'ojt_start_date' => $data['ojt_start_date'] ?? null, 'ojt_end_date' => $data['ojt_end_date'] ?? null, 'required_ojt_hours' => $data['required_ojt_hours'] ?? 486,
            'allow_past_attendance' => $data['allow_past_attendance'] ?? false,
        ];
    }

    private function studentPayload(Student $student): array
    {
        $hours = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));
        $required = max((float) $student->required_ojt_hours, 0);
        $activeRequirements = InternshipRequirement::activeChecklistForStudent($student->id);
        $approvedRequirements = $activeRequirements->where('status', 'approved')->count();
        $requirementTotal = $activeRequirements->count();
        $studentData = $student->toArray();
        $studentData['requirements'] = $activeRequirements->toArray();

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
        return AttendanceHours::total($record);
    }
}
