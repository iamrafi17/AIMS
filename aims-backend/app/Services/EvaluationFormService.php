<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\EvaluationCategory;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationForm;
use App\Models\EvaluationRatingOption;
use App\Models\EvaluationSubgroup;
use App\Models\Program;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvaluationFormService
{
    public function create(int $programId, int $userId, array $data): EvaluationForm
    {
        return DB::transaction(function () use ($programId, $userId, $data) {
            Program::whereKey($programId)->lockForUpdate()->firstOrFail();
            $version = (int) EvaluationForm::where('program_id', $programId)->where('academic_period_id', $data['academic_period_id'])->max('version') + 1;

            return EvaluationForm::create([
                'program_id' => $programId, 'academic_period_id' => $data['academic_period_id'],
                'name' => $data['name'], 'version' => $version, 'created_by' => $userId,
                'computation_method' => 'ratings_only',
            ])->fresh();
        }, 3);
    }

    public function editable(EvaluationForm $form, int $revision): void
    {
        abort_unless($form->status === 'draft', 409, 'Only draft forms can change. Duplicate this version to revise it.');
        abort_unless((int) $form->row_version === $revision, 409, 'This form changed in another tab. Reload before editing.');
    }

    public function deleteDraft(EvaluationForm $form, int $revision): void
    {
        DB::transaction(function () use ($form, $revision): void {
            Program::whereKey($form->program_id)->lockForUpdate()->firstOrFail();
            $locked = EvaluationForm::lockForUpdate()->findOrFail($form->id);
            $this->editable($locked, $revision);
            abort_if($locked->published_at !== null, 409, 'A previously published form cannot be deleted.');
            abort_if(Evaluation::where('evaluation_form_id', $locked->id)->exists(), 409, 'This form is used by an evaluation and cannot be deleted.');

            $this->replaceStructure($locked, []);
            EvaluationRatingOption::where('evaluation_form_id', $locked->id)->delete();
            $locked->delete();
        }, 3);
    }

    public function update(EvaluationForm $form, array $data): EvaluationForm
    {
        return DB::transaction(function () use ($form, $data) {
            $form = EvaluationForm::lockForUpdate()->findOrFail($form->id);
            $this->editable($form, (int) $data['row_version']);
            $clean = $this->validate($data);
            if (array_key_exists('categories', $clean)) {
                $this->replaceStructure($form, $clean['categories']);
            }
            if (array_key_exists('rating_options', $clean)) {
                EvaluationRatingOption::where('evaluation_form_id', $form->id)->delete();
                foreach ($clean['rating_options'] as $i => $option) {
                    EvaluationRatingOption::create([...$option, 'evaluation_form_id' => $form->id, 'sort_order' => $i]);
                }
            }
            unset($clean['categories'], $clean['rating_options']);
            $form->fill($clean);
            $form->row_version++;
            $form->save();

            return $form->full();
        }, 3);
    }

    public function validate(array $data): array
    {
        $clean = Validator::make($data, [
            'name' => 'sometimes|required|string|max:160',
            'rating_direction' => ['sometimes', 'nullable', Rule::in(['lower', 'higher'])],
            'allow_no_rating' => 'sometimes|boolean',
            'computation_method' => ['sometimes', 'nullable', Rule::in(['ratings_only', 'rated_item_mean', 'category_mean'])],
            'computation_settings' => 'sometimes|nullable|array:decimal_places,rounding,empty_category',
            'computation_settings.decimal_places' => 'sometimes|integer|between:0,3',
            'computation_settings.rounding' => ['sometimes', Rule::in(['half_up'])],
            'computation_settings.empty_category' => ['sometimes', Rule::in(['exclude', 'require_numeric'])],
            'rating_options' => 'sometimes|array|max:50',
            'rating_options.*.numeric_value' => ['required', 'regex:/^-?\d{1,7}(\.\d{1,3})?$/'],
            'rating_options.*.label' => 'required|string|max:500',
            'categories' => 'sometimes|array|max:100',
            'categories.*.title' => 'required|string|max:500',
            'categories.*.criteria' => 'present|array|max:1000',
            'categories.*.criteria.*.label' => 'required|string|max:4000',
            'categories.*.criteria.*.extra_input_label' => 'nullable|string|max:160',
            'categories.*.subgroups' => 'present|array|max:100',
            'categories.*.subgroups.*.title' => 'required|string|max:500',
            'categories.*.subgroups.*.criteria' => 'present|array|max:1000',
            'categories.*.subgroups.*.criteria.*.label' => 'required|string|max:4000',
            'categories.*.subgroups.*.criteria.*.extra_input_label' => 'nullable|string|max:160',
        ])->validate();
        $values = [];
        foreach ($clean['rating_options'] ?? [] as $i => $option) {
            $key = (string) BigDecimal::of($option['numeric_value'])->toScale(3);
            if (isset($values[$key])) {
                throw ValidationException::withMessages(["rating_options.$i.numeric_value" => 'Duplicate numeric rating. Values such as 1 and 1.00 are the same.']);
            }
            $values[$key] = true;
        }
        $count = 0;
        foreach ($clean['categories'] ?? [] as $category) {
            $count += count($category['criteria']);
            foreach ($category['subgroups'] as $group) {
                $count += count($group['criteria']);
            }
        }
        if ($count > 5000) {
            throw ValidationException::withMessages(['categories' => 'A form can have at most 5,000 criteria.']);
        }

        return $clean;
    }

    public function replaceStructure(EvaluationForm $form, array $categories): void
    {
        EvaluationCriterion::where('evaluation_form_id', $form->id)->delete();
        EvaluationSubgroup::where('evaluation_form_id', $form->id)->delete();
        EvaluationCategory::where('evaluation_form_id', $form->id)->delete();
        $order = 0;
        foreach ($categories as $i => $item) {
            $category = EvaluationCategory::create(['evaluation_form_id' => $form->id, 'title' => $item['title'], 'sort_order' => $i]);
            $add = function (array $criteria, ?int $subgroupId) use ($form, $category, &$order) {
                foreach ($criteria as $criterion) {
                    EvaluationCriterion::create([
                        'evaluation_form_id' => $form->id, 'evaluation_category_id' => $category->id,
                        'evaluation_subgroup_id' => $subgroupId, 'label' => $criterion['label'],
                        'extra_input_label' => $criterion['extra_input_label'] ?? null, 'sort_order' => $order++,
                    ]);
                }
            };
            $add($item['criteria'], null);
            foreach ($item['subgroups'] as $j => $group) {
                $subgroup = EvaluationSubgroup::create(['evaluation_form_id' => $form->id, 'evaluation_category_id' => $category->id, 'title' => $group['title'], 'sort_order' => $j]);
                $add($group['criteria'], $subgroup->id);
            }
        }
    }

    public function publish(EvaluationForm $form, int $revision, int $userId): EvaluationForm
    {
        return DB::transaction(function () use ($form, $revision, $userId) {
            Program::whereKey($form->program_id)->lockForUpdate()->firstOrFail();
            $form = EvaluationForm::lockForUpdate()->findOrFail($form->id);
            $this->editable($form, $revision);
            $form->load('ratingOptions', 'categories.subgroups.criteria', 'categories.criteria');
            $errors = [];
            if ($form->ratingOptions->count() < 2) {
                $errors['rating_options'] = 'At least two distinct numeric ratings are required.';
            }
            if (! $form->rating_direction) {
                $errors['rating_direction'] = 'Select whether lower or higher is better.';
            }
            if (! $form->computation_method) {
                $errors['computation_method'] = 'Select an official computation or ratings only.';
            }
            if (! $form->criteria()->exists()) {
                $errors['categories'] = 'At least one criterion is required.';
            }
            foreach ($form->categories as $category) {
                if ($category->criteria->isEmpty() && $category->subgroups->every(fn ($group) => $group->criteria->isEmpty())) {
                    $errors['categories'] = 'Remove empty categories before publishing.';
                }
                if ($category->subgroups->contains(fn ($group) => $group->criteria->isEmpty())) {
                    $errors['categories'] = 'Remove empty subgroups before publishing.';
                }
            }
            if ($form->computation_method !== 'ratings_only') {
                if (! isset($form->computation_settings['decimal_places']) || ($form->computation_settings['rounding'] ?? null) !== 'half_up') {
                    $errors['computation_settings'] = 'Select decimal places and half-up rounding for calculated results.';
                }
                if ($form->computation_method === 'category_mean' && ! isset($form->computation_settings['empty_category'])) {
                    $errors['computation_settings.empty_category'] = 'Select how categories without numeric ratings are handled.';
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            EvaluationForm::where('program_id', $form->program_id)->where('academic_period_id', $form->academic_period_id)
                ->where('status', 'published')->update(['status' => 'archived', 'updated_at' => now()]);
            $form->update(['status' => 'published', 'published_by' => $userId, 'published_at' => now(), 'row_version' => $form->row_version + 1]);

            return $form->full();
        }, 3);
    }

    public function structure(EvaluationForm $form): array
    {
        return $form->full()->categories->map(fn ($c) => [
            'title' => $c->title, 'criteria' => $c->criteria->map->only(['label', 'extra_input_label'])->all(),
            'subgroups' => $c->subgroups->map(fn ($g) => ['title' => $g->title, 'criteria' => $g->criteria->map->only(['label', 'extra_input_label'])->all()])->all(),
        ])->all();
    }
}
