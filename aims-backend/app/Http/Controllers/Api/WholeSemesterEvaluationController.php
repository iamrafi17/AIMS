<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\OjtEnrollment;
use App\Models\StudentInternship;
use App\Services\EvaluationWorkflow;
use App\Support\EvaluationAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class WholeSemesterEvaluationController extends Controller
{
    public function __construct(private EvaluationWorkflow $workflow) {}

    public function students(Request $request)
    {
        $query = EvaluationAccess::assigned($request->user());
        $this->filterPeriod($query, $request);
        $counts = (clone $query)->select('student_internships.id');
        $assigned = (clone $query)->count();
        $submitted = Evaluation::whereIn('student_internship_id', $counts)->where('evaluation_type', 'whole_semester')->where('status', 'submitted')->count();
        $this->search($query, $request);
        $internships = $this->loadList($query)->latest('id')->paginate(20);
        // One scoped lookup for form availability, rather than one query per intern.
        $forms = EvaluationForm::where('status', 'published')->get(['id', 'program_id', 'academic_period_id'])->keyBy(fn ($f) => $f->program_id.':'.$f->academic_period_id);
        $internships->through(fn ($internship) => $this->internPayload($internship, $forms, $request->user()->id));

        return response()->json(['students' => $internships, 'overview' => ['assigned' => $assigned, 'submitted' => $submitted, 'remaining' => $assigned - $submitted], 'periods' => $this->periodsFor(EvaluationAccess::assigned($request->user()))]);
    }

    public function start(Request $request, int $internship)
    {
        return response()->json($this->workflow->detail($this->workflow->start($request->user(), $internship)));
    }

    public function show(Request $request, Evaluation $evaluation)
    {
        EvaluationAccess::authorizeEvaluator($request->user(), $evaluation);

        return response()->json($evaluation->evaluation_type === 'whole_semester' ? $this->workflow->detail($evaluation) : $this->legacyDetail($evaluation));
    }

    public function save(Request $request, Evaluation $evaluation)
    {
        $data = $request->validate([
            'row_version' => 'required|integer|min:0', 'responses' => 'sometimes|array|max:5000',
            'responses.*.criterion_id' => 'required|integer|distinct',
            'responses.*.rating_option_id' => 'present|nullable|integer', 'responses.*.is_no_rating' => 'required|boolean',
            'responses.*.extra_text' => 'sometimes|nullable|string|max:2000',
            'feedback' => 'sometimes|nullable|string|max:5000', 'recommendations' => 'sometimes|nullable|string|max:5000',
        ]);
        abort_unless($evaluation->evaluation_type === 'whole_semester', 409, 'Legacy evaluations are read-only.');
        $saved = $this->workflow->save($request->user(), $evaluation, $data);

        return response()->json([...$this->workflow->detail($saved), 'message' => 'Evaluation draft saved.']);
    }

    public function review(Request $request, Evaluation $evaluation)
    {
        return $this->show($request, $evaluation);
    }

    public function submit(Request $request, Evaluation $evaluation)
    {
        $data = $request->validate(['row_version' => 'required|integer|min:0']);
        abort_unless($evaluation->evaluation_type === 'whole_semester', 409, 'Legacy evaluations are read-only.');

        return response()->json([...$this->workflow->detail($this->workflow->submit($request->user(), $evaluation, $data['row_version'])), 'message' => 'Evaluation submitted and locked.']);
    }

    public function results(Request $request)
    {
        $query = EvaluationAccess::resultsInternships($request->user());
        $this->filterPeriod($query, $request);
        if ($request->filled('section_id')) {
            $query->where(fn ($q) => $q->where('academic_section_id', $request->integer('section_id'))
                ->orWhere(fn ($fallback) => $fallback->whereNull('academic_section_id')->whereHas('enrollment', fn ($e) => $e->where('academic_section_id', $request->integer('section_id')))));
        }
        $this->search($query, $request);
        $legacyScope = clone $query;
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'not_started') {
                $query->whereDoesntHave('evaluations', fn ($e) => $e->where('evaluation_type', 'whole_semester'));
            } elseif (in_array($status, ['draft', 'submitted'], true)) {
                $query->whereHas('evaluations', fn ($e) => $e->where('evaluation_type', 'whole_semester')->where('status', $status));
            }
        }
        $rows = $this->loadList(clone $query)->latest('id')->paginate(20);
        $submittedEvaluations = new Collection($rows->getCollection()->flatMap(fn ($i) => $i->evaluations->where('status', 'submitted'))->all());
        $submittedEvaluations->load(['responses', 'form.criteria', 'form.program', 'form.academicPeriod', 'form.ratingOptions', 'form.categories.criteria', 'form.categories.subgroups.criteria']);
        $rows->through(fn ($internship) => $this->internPayload($internship));
        $scope = EvaluationAccess::resultsInternships($request->user());
        $legacy = Evaluation::whereIn('student_internship_id', $legacyScope->select('student_internships.id'))->whereIn('evaluation_type', ['midterm', 'final'])
            ->whereIn('status', ['submitted', 'finalized'])->with('student')->latest('id')->paginate(20, ['*'], 'legacy_page');
        // Hide score columns in lists; detailed grades require the submitted-result endpoint.
        $legacy->through(fn ($e) => ['id' => $e->id, 'student' => $e->student?->full_name, 'type' => $e->evaluation_type, 'submitted_at' => $e->submitted_at]);

        return response()->json(['results' => $rows, 'legacy' => $legacy, 'periods' => $this->periodsFor($scope), 'sections' => EvaluationAccess::visibleSections($request->user())->orderBy('name')->get()]);
    }

    public function result(Request $request, Evaluation $evaluation)
    {
        abort_unless(EvaluationAccess::resultsInternships($request->user())->whereKey($evaluation->student_internship_id)->exists(), 404);
        abort_unless(in_array($evaluation->status, ['submitted', 'finalized'], true), 404, 'Draft answers are private.');

        return response()->json($evaluation->evaluation_type === 'whole_semester' ? $this->workflow->detail($evaluation) : $this->legacyDetail($evaluation));
    }

    public function legacy(Request $request)
    {
        return response()->json(['legacy' => Evaluation::where('supervisor_id', $request->user()->id)->whereIn('evaluation_type', ['midterm', 'final'])->with('student')->latest('id')->paginate(20)]);
    }

    private function legacyDetail(Evaluation $evaluation): array
    {
        return ['evaluation' => $evaluation, 'legacy' => true, 'student' => ['name' => $evaluation->student?->full_name], 'evaluator' => ['name' => $evaluation->supervisor?->name]];
    }

    private function search($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = mb_substr($request->string('search'), 0, 160);
            $query->whereHas('student', fn ($s) => $s->where(fn ($q) => $q->where('first_name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%")->orWhere('student_id', 'like', "%$search%")));
        }
    }

    private function loadList($query)
    {
        return $query->with(['student', 'academicSection.program', 'academicSection.academicPeriod', 'enrollment.academicSection.program', 'enrollment.academicSection.academicPeriod', 'currentPlacement.departmentProgram.department.hte', 'currentPlacement.supervisor.supervisorProfile.user',
            'evaluations' => fn ($q) => $q->where('evaluation_type', 'whole_semester')->select('id', 'student_internship_id', 'supervisor_id', 'status', 'submitted_at', 'evaluation_form_id')]);
    }

    private function internPayload(StudentInternship $internship, $forms = null, ?int $userId = null): array
    {
        $section = EvaluationAccess::section($internship);
        $evaluation = $internship->evaluations->first();
        $foreignOwner = $evaluation && $userId && (int) $evaluation->supervisor_id !== $userId;
        $available = $section && $forms?->has($section->program_id.':'.$section->academic_period_id);
        $status = $evaluation?->status ?? ($forms !== null && ! $available ? 'form_unavailable' : 'not_started');
        $supervisor = $internship->currentPlacement?->supervisor;

        return [
            'id' => $internship->id, 'student_id' => $internship->student?->student_id,
            'name' => $internship->student?->full_name, 'program' => $section?->program?->code,
            'section' => $section?->name, 'academic_section_id' => $section?->id,
            'period' => $section?->academicPeriod, 'hte' => $internship->currentPlacement?->departmentProgram?->department?->hte?->name,
            'department' => $internship->currentPlacement?->departmentProgram?->department?->department_name,
            'supervisor' => $supervisor?->full_name, 'evaluation_id' => $foreignOwner ? null : $evaluation?->id,
            'status' => $status, 'submitted_at' => $evaluation?->submitted_at,
            'overall' => $evaluation?->status === 'submitted' && $evaluation->relationLoaded('form') ? $this->workflow->summary($evaluation)['overall'] : null,
            'rating_direction' => $evaluation?->relationLoaded('form') ? $evaluation->form?->rating_direction : null,
            'blocked_reason' => $foreignOwner ? 'A previous supervisor owns this evaluation. Contact the Coordinator.' : ($status === 'form_unavailable' ? 'The Coordinator must publish a form for this internship program and period.' : null),
        ];
    }

    private function periodsFor($query)
    {
        $sectionIds = (clone $query)->select('academic_section_id');
        $fallbackSections = OjtEnrollment::whereIn('id', (clone $query)->whereNull('academic_section_id')->select('ojt_enrollment_id'))->select('academic_section_id');

        return AcademicPeriod::whereHas('sections', fn ($s) => $s->where(fn ($q) => $q->whereIn('id', $sectionIds)->orWhereIn('id', $fallbackSections)))->orderByDesc('start_date')->get();
    }

    private function filterPeriod($query, Request $request): void
    {
        if ($request->filled('academic_period_id')) {
            $periodId = $request->integer('academic_period_id');
            $query->where(fn ($q) => $q->whereHas('academicSection', fn ($s) => $s->where('academic_period_id', $periodId))
                ->orWhere(fn ($fallback) => $fallback->whereNull('academic_section_id')->whereHas('enrollment.academicSection', fn ($s) => $s->where('academic_period_id', $periodId))));
        }
    }
}
