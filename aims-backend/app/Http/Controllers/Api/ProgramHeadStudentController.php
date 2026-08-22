<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\InternshipRequirement;
use App\Models\Student;
use App\Support\AttendanceHours;
use App\Support\ProgramAccess;
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
        $query = Student::with(['user', 'college', 'program', 'hte', 'supervisor:id,name', 'attendance', 'requirements'])
            ->where('program_id', $programId)
            ->when($sectionId, fn ($studentQuery) => $studentQuery->where('academic_section_id', $sectionId))
            ->orderBy('last_name');

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('student_id', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('internship_status', $filters['status']);
        }

        $students = $query->get()->map(fn (Student $student) => $this->payload($student));

        return response()->json([
            'scope' => [
                'college' => $request->user()->college,
                'program' => $request->user()->program,
                'sections' => AcademicSection::where('program_id', $programId)
                    ->where('is_active', true)
                    ->orderByDesc('academic_year')
                    ->orderBy('year_level')
                    ->orderBy('name')
                    ->get(['id', 'program_id', 'name', 'year_level', 'academic_year']),
            ],
            'students' => $students,
            'summary' => [
                'total' => $students->count(),
                'active' => $students->where('internship_status', 'active')->count(),
                'near_completion' => $students->where('progress', '>=', 80)->where('progress', '<', 100)->count(),
                'completed' => $students->where('internship_status', 'completed')->count(),
                'needs_attention' => $students->where('progress', '<', 40)->where('internship_status', 'active')->count(),
            ],
        ]);
    }

    private function payload(Student $student): array
    {
        $hours = $student->attendance->sum(function (Attendance $record) {
            return AttendanceHours::total($record);
        });
        $required = max((float) $student->required_ojt_hours, 1);
        InternshipRequirement::ensureForStudent($student->id);
        $activeRequirements = InternshipRequirement::activeChecklistForStudent($student->id);

        return [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'name' => trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->join(' ')),
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
            'progress' => round(min(($hours / $required) * 100, 100), 1),
            'attendance_days' => $student->attendance->whereIn('status', ['present', 'late'])->count(),
            'requirements_approved' => $activeRequirements->where('status', 'approved')->count(),
            'requirements_total' => $activeRequirements->count(),
        ];
    }
}
