<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationResponse;
use App\Models\Program;
use App\Models\User;
use App\Support\EvaluationAccess;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationWorkflow
{
    public function start(User $user, int $internshipId): Evaluation
    {
        return DB::transaction(function () use ($user, $internshipId) {
            $internship = EvaluationAccess::assigned($user)->lockForUpdate()->findOrFail($internshipId);
            $existing = Evaluation::where('student_internship_id', $internshipId)->where('evaluation_type', 'whole_semester')->first();
            if ($existing) {
                // No automatic transfer of another evaluator's draft or submitted work.
                abort_unless((int) $existing->supervisor_id === $user->id, 409, 'An evaluation belongs to a previous supervisor. An explicit reassignment policy is required.');

                return $existing;
            }
            $section = EvaluationAccess::section($internship);
            if (! $section || ! $section->academic_period_id) {
                throw ValidationException::withMessages(['internship' => 'This internship needs a valid academic section and period. Ask the Coordinator to check its setup.']);
            }
            Program::whereKey($section->program_id)->lockForUpdate()->firstOrFail();
            $form = EvaluationForm::where('program_id', $section->program_id)->where('academic_period_id', $section->academic_period_id)->where('status', 'published')->first();
            if (! $form) {
                throw ValidationException::withMessages(['form' => 'No published form is available for this internship program and period.']);
            }

            return Evaluation::create([
                'student_id' => $internship->student_id, 'student_internship_id' => $internship->id,
                'supervisor_id' => $user->id, 'evaluation_form_id' => $form->id,
                'evaluation_type' => 'whole_semester', 'status' => 'draft',
            ])->fresh();
        }, 3);
    }

    public function save(User $user, Evaluation $evaluation, array $data): Evaluation
    {
        return DB::transaction(function () use ($user, $evaluation, $data) {
            $evaluation = Evaluation::lockForUpdate()->findOrFail($evaluation->id);
            EvaluationAccess::authorizeEvaluator($user, $evaluation, true);
            $this->revision($evaluation, $data['row_version']);
            $form = $evaluation->form;
            $criteria = $form->criteria->keyBy('id');
            $options = $form->ratingOptions->keyBy('id');
            foreach ($data['responses'] ?? [] as $index => $answer) {
                $criterion = $criteria->get($answer['criterion_id']);
                $errorKey = 'responses.'.$index;
                if (! $criterion) {
                    throw ValidationException::withMessages([$errorKey => 'Criterion does not belong to this form.']);
                }
                $noRating = $answer['is_no_rating'] ?? false;
                $optionId = $answer['rating_option_id'] ?? null;
                if (($noRating && (! $form->allow_no_rating || $optionId !== null)) || ($optionId !== null && ! $options->has($optionId))) {
                    throw ValidationException::withMessages([$errorKey => 'Choose an allowed rating from this form.']);
                }
                if (! $criterion->extra_input_label && filled($answer['extra_text'] ?? null)) {
                    throw ValidationException::withMessages([$errorKey.'.extra_text' => 'This criterion has no additional text field.']);
                }
                $query = EvaluationResponse::where('evaluation_id', $evaluation->id)->where('evaluation_criterion_id', $criterion->id);
                if (! $noRating && $optionId === null) {
                    $query->delete(); // Explicit null + false clears; omitted criteria remain unchanged.

                    continue;
                }
                $existing = $query->first();
                EvaluationResponse::updateOrCreate([
                    'evaluation_id' => $evaluation->id, 'evaluation_criterion_id' => $criterion->id,
                ], [
                    'evaluation_form_id' => $form->id, 'evaluation_rating_option_id' => $optionId,
                    'is_no_rating' => $noRating,
                    'extra_text' => array_key_exists('extra_text', $answer) ? $answer['extra_text'] : $existing?->extra_text,
                ]);
            }
            foreach (['feedback', 'recommendations'] as $field) {
                if (array_key_exists($field, $data)) {
                    $evaluation->$field = $data[$field];
                }
            }
            $evaluation->row_version++;
            $evaluation->save();

            return $evaluation->fresh();
        }, 3);
    }

    public function revision(Evaluation $evaluation, int $revision): void
    {
        abort_unless($evaluation->status === 'draft', 409, 'Submitted evaluations are read-only.');
        abort_unless((int) $evaluation->row_version === $revision, 409, 'This evaluation changed in another tab. Your unsaved answers are retained; reload to resolve the conflict.');
    }

    public function summary(Evaluation $evaluation): array
    {
        $form = $evaluation->form->full();
        $criteria = $form->criteria;
        $responses = $evaluation->responses->keyBy('evaluation_criterion_id');
        $options = $form->ratingOptions->keyBy('id');
        $missing = $criteria->reject(fn ($c) => $responses->has($c->id))->map->only(['id', 'label', 'evaluation_category_id'])->values()->all();
        $groups = [];
        $sum = BigRational::zero();
        $numericCount = 0;
        foreach ($form->categories as $category) {
            $categorySum = BigRational::zero();
            $count = 0;
            foreach ($criteria->where('evaluation_category_id', $category->id) as $criterion) {
                $response = $responses->get($criterion->id);
                if ($response && ! $response->is_no_rating) {
                    $categorySum = $categorySum->plus($options->get($response->evaluation_rating_option_id)->numeric_value);
                    $count++;
                }
            }
            $groups[] = ['id' => $category->id, 'title' => $category->title, 'numeric_count' => $count, 'mean' => $count ? $categorySum->dividedBy($count) : null];
            $sum = $sum->plus($categorySum);
            $numericCount += $count;
        }
        $errors = [];
        if ($missing) {
            $errors[] = 'Resolve every criterion before submitting.';
        }
        if (! $numericCount) {
            $errors[] = 'At least one numeric rating is required.';
        }
        if ($form->computation_method === 'category_mean' && ($form->computation_settings['empty_category'] ?? null) === 'require_numeric'
            && collect($groups)->contains(fn ($g) => ! $g['numeric_count'])) {
            $errors[] = 'Each category requires at least one numeric rating.';
        }
        $mean = null;
        if ($numericCount && $form->computation_method === 'rated_item_mean') {
            $mean = $sum->dividedBy($numericCount);
        }
        if ($numericCount && $form->computation_method === 'category_mean') {
            $eligible = collect($groups)->filter(fn ($g) => $g['numeric_count']);
            $mean = BigRational::zero();
            foreach ($eligible as $group) {
                $mean = $mean->plus($group['mean']);
            }
            $mean = $mean->dividedBy($eligible->count());
        }
        $places = $form->computation_settings['decimal_places'] ?? 2;
        $round = fn ($number) => $number === null ? null : (string) $number->toScale($places, RoundingMode::HALF_UP);

        return [
            'method' => $form->computation_method, 'rating_direction' => $form->rating_direction,
            'total_criteria' => $criteria->count(), 'answered' => $responses->count(),
            'numeric_count' => $numericCount, 'no_rating_count' => $responses->where('is_no_rating', true)->count(),
            'missing' => $missing, 'errors' => $errors, 'can_submit' => ! $errors, 'overall' => $round($mean),
            'categories' => array_map(fn ($g) => [...$g, 'mean' => $form->computation_method === 'ratings_only' ? null : $round($g['mean'])], $groups),
        ];
    }

    public function submit(User $user, Evaluation $evaluation, int $revision): Evaluation
    {
        return DB::transaction(function () use ($user, $evaluation, $revision) {
            $evaluation = Evaluation::lockForUpdate()->findOrFail($evaluation->id);
            EvaluationAccess::authorizeEvaluator($user, $evaluation, true);
            if ($evaluation->status === 'submitted') {
                return $evaluation;
            }
            $this->revision($evaluation, $revision);
            $summary = $this->summary($evaluation);
            if (! $summary['can_submit']) {
                throw ValidationException::withMessages(['responses' => $summary['errors']]);
            }
            $evaluation->update(['status' => 'submitted', 'submitted_at' => now(), 'row_version' => $evaluation->row_version + 1]);

            return $evaluation->fresh();
        }, 3);
    }

    public function detail(Evaluation $evaluation): array
    {
        $internship = $evaluation->internship;

        return [
            'evaluation' => $evaluation->load('responses'), 'form' => $evaluation->form->full(),
            'summary' => $this->summary($evaluation),
            'evaluator' => ['id' => $evaluation->supervisor_id, 'name' => $evaluation->supervisor?->name],
            'student' => ['name' => $evaluation->student?->full_name, 'student_id' => $evaluation->student?->student_id],
            'internship' => [
                'id' => $internship?->id, 'section' => $internship ? EvaluationAccess::section($internship)?->name : null,
                'hte' => $internship?->currentPlacement?->departmentProgram?->department?->hte?->name,
                'department' => $internship?->currentPlacement?->departmentProgram?->department?->department_name,
            ],
        ];
    }
}
