<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\EvaluationForm;
use App\Models\Program;
use App\Services\EvaluationCsvImport;
use App\Services\EvaluationFormService;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EvaluationFormController extends Controller
{
    public function __construct(private EvaluationFormService $forms) {}

    private function authorizeForm(Request $request, EvaluationForm $form): void
    {
        abort_unless((int) $form->program_id === ProgramAccess::programId($request->user()), 404);
    }

    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $forms = EvaluationForm::where('program_id', $programId)->with('academicPeriod')->withCount('criteria')
            ->when($request->filled('academic_period_id'), fn ($q) => $q->where('academic_period_id', $request->integer('academic_period_id')))
            ->latest('id')->paginate(20);

        return response()->json(['forms' => $forms, 'program' => Program::findOrFail($programId), 'periods' => AcademicPeriod::orderByDesc('start_date')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:160', 'academic_period_id' => 'required|integer|exists:academic_periods,id']);

        return response()->json(['form' => $this->forms->create(ProgramAccess::programId($request->user()), $request->user()->id, $data)->full()], 201);
    }

    public function show(Request $request, EvaluationForm $form)
    {
        $this->authorizeForm($request, $form);

        return response()->json(['form' => $form->full()]);
    }

    public function update(Request $request, EvaluationForm $form)
    {
        $this->authorizeForm($request, $form);
        $request->validate(['row_version' => 'required|integer|min:0']);

        return response()->json(['form' => $this->forms->update($form, $request->all()), 'message' => 'Form draft saved.']);
    }

    public function publish(Request $request, EvaluationForm $form)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate(['row_version' => 'required|integer|min:0']);

        return response()->json(['form' => $this->forms->publish($form, $data['row_version'], $request->user()->id), 'message' => 'Form published. Existing evaluations keep their original version.']);
    }

    public function destroy(Request $request, EvaluationForm $form)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate(['row_version' => 'required|integer|min:0']);
        $this->forms->deleteDraft($form, $data['row_version']);

        return response()->json(['message' => 'Evaluation draft deleted.']);
    }

    public function duplicate(Request $request, EvaluationForm $form)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate(['academic_period_id' => 'sometimes|required|integer|exists:academic_periods,id']);
        $copy = DB::transaction(function () use ($form, $data, $request) {
            $copy = $this->forms->create($form->program_id, $request->user()->id, ['name' => $form->name, 'academic_period_id' => $data['academic_period_id'] ?? $form->academic_period_id]);

            return $this->forms->update($copy, [
                'row_version' => 0, 'categories' => $this->forms->structure($form),
                'rating_options' => $form->ratingOptions->map->only(['numeric_value', 'label'])->all(),
                ...$form->only(['rating_direction', 'allow_no_rating', 'computation_method', 'computation_settings']),
            ]);
        });

        return response()->json(['form' => $copy, 'message' => 'New draft version created.'], 201);
    }

    public function archive(Request $request, EvaluationForm $form)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate(['row_version' => 'required|integer|min:0']);
        DB::transaction(function () use ($form, $data) {
            Program::whereKey($form->program_id)->lockForUpdate()->firstOrFail();
            $locked = EvaluationForm::lockForUpdate()->findOrFail($form->id);
            abort_unless($locked->status === 'published' && (int) $locked->row_version === $data['row_version'], 409, 'The form changed or is not published. Reload the list.');
            $locked->update(['status' => 'archived', 'row_version' => $locked->row_version + 1]);
        });

        return response()->json(['message' => 'Form archived for new starts. Existing evaluations are preserved.']);
    }

    public function previewImport(Request $request, EvaluationForm $form, EvaluationCsvImport $csv)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate([
            'file' => 'required|file|max:2048|extensions:csv,txt', 'row_version' => 'required|integer|min:0',
            'layout' => ['required', Rule::in(['report', 'normalized'])],
            'category_column' => 'required|integer|between:0,199', 'criterion_column' => 'required|integer|between:0,199',
            'subgroup_column' => 'nullable|integer|between:0,199',
            'start_row' => 'nullable|integer|between:1,10000', 'end_row' => 'nullable|integer|between:1,10000|gte:start_row',
        ]);
        $this->forms->editable($form, (int) $data['row_version']);
        $preview = $csv->parse($request->file('file')->getRealPath(), $data);
        $token = null;
        if (! $preview['errors']) {
            $token = (string) Str::uuid();
            Cache::put('evaluation-import:'.$token, ['user_id' => $request->user()->id, 'form_id' => $form->id, 'row_version' => $form->row_version, 'categories' => $preview['categories']], now()->addMinutes(15));
        }

        return response()->json([...$preview, 'token' => $token]);
    }

    public function confirmImport(Request $request, EvaluationForm $form)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate(['token' => 'required|uuid', 'mode' => ['required', Rule::in(['append', 'replace'])]]);
        $cached = Cache::get('evaluation-import:'.$data['token']);
        abort_unless($cached && $cached['user_id'] === $request->user()->id && $cached['form_id'] === $form->id, 422, 'Import preview expired or belongs to another session. Preview the file again.');
        $updated = DB::transaction(function () use ($form, $cached, $data) {
            $locked = EvaluationForm::lockForUpdate()->findOrFail($form->id);
            $this->forms->editable($locked, $cached['row_version']);
            $categories = $data['mode'] === 'append' ? [...$this->forms->structure($locked), ...$cached['categories']] : $cached['categories'];

            return $this->forms->update($locked, ['row_version' => $cached['row_version'], 'categories' => $categories]);
        });
        Cache::forget('evaluation-import:'.$data['token']);

        return response()->json(['form' => $updated, 'message' => 'Criteria imported into the draft. Review before publishing.']);
    }
}
