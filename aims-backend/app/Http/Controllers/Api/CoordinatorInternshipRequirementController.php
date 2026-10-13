<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\InternshipRequirement;
use App\Models\ProgramRequirement;
use App\Models\Student;
use App\Support\ProgramAccess;
use App\Support\RequirementOverview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoordinatorInternshipRequirementController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['for_review', 'incomplete', 'needs_resubmission', 'completed'])],
            'section_id' => ['nullable', 'integer'],
            'requirement_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $user = $request->user();
        $programId = ProgramAccess::programId($user);
        $definitions = ProgramRequirement::where('program_id', $programId)->where('is_active', true)
            ->orderBy('sort_order')->get(['id', 'name']);
        $students = ProgramAccess::studentQuery($user)->with(['user', 'currentInternship.academicSection'])
            ->orderBy('last_name')->orderBy('first_name')->get();
        InternshipRequirement::ensureForStudents($students->pluck('id'));
        $students->load('requirements.definition');

        $selectedRequirementId = isset($filters['requirement_id']) ? (int) $filters['requirement_id'] : null;
        $all = $students->map(function (Student $student) use ($selectedRequirementId) {
            $section = $student->currentInternship?->academicSection;
            $requirements = $student->requirements->filter(fn ($item) => ! $item->program_requirement_id || $item->definition?->is_active)->values();
            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'email' => $student->user?->email,
                'section_id' => $section?->id,
                'section' => $section?->name,
                'overall' => RequirementOverview::forStudent($requirements),
                'overview' => RequirementOverview::forStudent($selectedRequirementId
                    ? $requirements->where('program_requirement_id', $selectedRequirementId)
                    : $requirements),
                'requirements' => $requirements,
            ];
        });
        $summary = [
            'total' => $all->count(),
            'for_review' => $all->where('overall.status', 'for_review')->count(),
            'incomplete' => $all->where('overall.status', 'incomplete')->count(),
            'needs_resubmission' => $all->where('overall.status', 'needs_resubmission')->count(),
            'completed' => $all->where('overall.status', 'completed')->count(),
        ];

        $filtered = $all->filter(function ($student) use ($filters) {
            $search = mb_strtolower(trim($filters['search'] ?? ''));
            return (! $search || str_contains(mb_strtolower($student['name'].' '.$student['student_id'].' '.$student['email']), $search))
                && (empty($filters['status']) || $student['overview']['status'] === $filters['status'])
                && (empty($filters['section_id']) || $student['section_id'] === (int) $filters['section_id'])
                && (empty($filters['requirement_id']) || $student['requirements']->contains('program_requirement_id', (int) $filters['requirement_id']));
        })->values();
        $perPage = (int) ($filters['per_page'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);

        return response()->json([
            'academic_period' => AcademicPeriod::where('status', 'active')->first(['academic_year', 'semester']),
            'summary' => $summary,
            'sections' => ProgramAccess::sectionQuery($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'definitions' => $definitions,
            'data' => $filtered->forPage($page, $perPage)->map(fn ($student) => collect($student)->except(['requirements', 'overall']))->values(),
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($filtered->count() / $perPage)),
            'total' => $filtered->count(),
        ]);
    }

    public function show(Request $request, Student $student)
    {
        ProgramAccess::authorizeStudent($request->user(), $student);
        $student->load(['user', 'currentInternship.academicSection']);
        InternshipRequirement::ensureForStudent($student->id);
        $student->load(['requirements.definition', 'requirements.reviewer']);
        $requirements = $student->requirements->filter(fn ($item) => ! $item->program_requirement_id || $item->definition?->is_active)->values();

        return response()->json([
            'id' => $student->id,
            'student_id' => $student->student_id,
            'name' => trim($student->first_name.' '.$student->last_name),
            'email' => $student->user?->email,
            'section' => $student->currentInternship?->academicSection?->name,
            'overview' => RequirementOverview::forStudent($requirements),
            'requirements' => $requirements,
        ]);
    }
}
