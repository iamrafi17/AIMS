<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\InternshipRequirement;
use App\Models\Student;
use App\Support\AttendanceHours;
use App\Support\ProgramAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramHeadStudentController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'completed', 'dropped'])],
            'academic_section_id' => ['nullable', 'integer', Rule::exists('academic_sections', 'id')->where(fn ($query) => $query
                ->where('program_id', $programId)
                ->where('is_active', true))],
        ]);
        $sectionId = isset($filters['academic_section_id']) ? (int) $filters['academic_section_id'] : null;
        $query = Student::with(['user', 'college', 'program', 'hte', 'supervisor:id,role_id', 'attendance', 'requirements'])
            ->forProgram($programId)
            ->when($sectionId, fn ($studentQuery) => $studentQuery->forAcademicSection($sectionId))
            ->orderBy('last_name');

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('student_id', 'like', "%{$search}%")
                    ->orWhere(function ($nameQuery) use ($search) {
                        foreach (preg_split('/\s+/', $search) as $part) {
                            $nameQuery->where(function ($partQuery) use ($part) {
                                $partQuery->where('first_name', 'like', "%{$part}%")
                                    ->orWhere('middle_name', 'like', "%{$part}%")
                                    ->orWhere('last_name', 'like', "%{$part}%");
                            });
                        }
                    });
            });
        }
        if (filled($filters['status'] ?? null)) {
            $query->forInternshipStatus($filters['status']);
        }

        $students = $query->get()->map(fn (Student $student) => $this->payload($student));

        return response()->json([
            'scope' => [
                'college' => $request->user()->college,
                'program' => $request->user()->program,
                'sections' => AcademicSection::where('program_id', $programId)
                    ->where('is_active', true)
                    ->newestPeriodFirst()
                    ->orderBy('year_level')
                    ->orderBy('name')
                    ->get(['id', 'program_id', 'academic_period_id', 'name', 'year_level']),
            ],
            'students' => $students,
            'summary' => [
                'total' => $students->count(),
                'active' => $students->where('internship_status', 'active')->count(),
                'near_completion' => $students->where('internship_status', 'active')->where('progress', '>=', 80)->where('progress', '<', 100)->count(),
                'completed' => $students->where('internship_status', 'completed')->count(),
                'needs_attention' => $students->where('needs_attention', true)->count(),
            ],
        ]);
    }

    private function payload(Student $student): array
    {
        $hours = $student->attendance->sum(function (Attendance $record) {
            return AttendanceHours::total($record);
        });
        $required = max((float) $student->required_ojt_hours, 1);
        $progress = round(min(($hours / $required) * 100, 100), 1);
        InternshipRequirement::ensureForStudent($student->id);
        $activeRequirements = InternshipRequirement::activeChecklistForStudent($student->id);

        return [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'name' => trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->join(' ')),
            'display_name' => trim(collect([$student->first_name, $student->last_name])->filter()->join(' ')),
            'avatar_url' => $student->user?->avatar_url,
            'college' => $student->college?->code,
            'program' => $student->program?->code,
            'section' => $student->section,
            'hte' => $student->hte?->name,
            'supervisor' => $student->supervisor?->name,
            'registration_status' => $student->registration_status,
            'internship_status' => $student->internship_status,
            'schedule_status' => $student->schedule_status,
            'rendered_hours' => round($hours, 1),
            'required_hours' => round($required, 1),
            'progress' => $progress,
            'needs_attention' => $this->needsAttention($student, $progress),
            'attendance_days' => $student->attendance->whereIn('status', ['present', 'late'])->count(),
            'requirements_approved' => $activeRequirements->where('status', 'approved')->count(),
            'requirements_total' => $activeRequirements->count(),
        ];
    }

    private function needsAttention(Student $student, float $progress): bool
    {
        if ($student->internship_status !== 'active' || $progress >= 100
            || ! $student->ojt_start_date || ! $student->ojt_end_date) {
            return false;
        }

        $start = Carbon::parse($student->ojt_start_date)->startOfDay();
        $end = Carbon::parse($student->ojt_end_date)->startOfDay();
        $today = today();

        if ($end->lt($today)) {
            return true;
        }
        if ($end->lte($start) || $today->lt($start->copy()->addDays(7))) {
            return false;
        }

        $scheduledDays = $start->diffInDays($end);
        $elapsedDays = min($scheduledDays, $start->diffInDays($today));
        $expectedProgress = $elapsedDays / $scheduledDays * 100;

        return $expectedProgress - $progress > 20;
    }
}
