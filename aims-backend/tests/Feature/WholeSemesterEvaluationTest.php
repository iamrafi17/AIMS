<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\College;
use App\Models\Evaluation;
use App\Models\HTE;
use App\Models\HteDepartment;
use App\Models\HteDepartmentProgram;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentInternship;
use App\Models\StudentInternshipPlacement;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\EvaluationCsvImport;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WholeSemesterEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function setupIntern(): array
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Systems', 'code' => 'BSIS', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $section = AcademicSection::create(['program_id' => $program->id, 'academic_period_id' => $this->academicPeriod()->id, 'name' => 'BSIS 4B', 'year_level' => 4, 'is_active' => true]);
        DB::table('coordinator_section_assignments')->insert(['coordinator_id' => $coordinator->id, 'academic_section_id' => $section->id, 'created_at' => now(), 'updated_at' => now()]);
        $student = Student::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id, 'student_id' => '2026-0001',
            'first_name' => 'Ralph', 'last_name' => 'Intern', 'academic_section_id' => $section->id,
            'college_id' => $college->id, 'program_id' => $program->id, 'academic_period_id' => $section->academic_period_id,
            'year_level' => 4, 'section' => 'BSIS 4B', 'registration_status' => 'approved',
        ]);
        $internship = $student->currentInternship ?? StudentInternship::create(['student_id' => $student->id, 'academic_section_id' => $section->id, 'status' => 'active', 'is_current' => true]);
        $hte = HTE::create(['name' => 'Training Office', 'address' => 'Santa Cruz', 'contact_person' => 'Office Head', 'contact_email' => 'office@example.test', 'contact_number' => '09170000000', 'is_active' => true]);
        $department = HteDepartment::create(['hte_id' => $hte->id, 'department_name' => 'IT', 'normalized_name' => 'it']);
        $link = HteDepartmentProgram::create(['hte_department_id' => $department->id, 'program_id' => $program->id, 'is_active' => true]);
        $assignment = Supervisor::create(['hte_department_id' => $department->id, 'full_name' => 'Supervisor One', 'user_id' => $supervisor->id, 'is_active' => true]);
        $supervisor->refresh()->update(['profile_completed_at' => now(), 'requires_security_setup' => false, 'requires_password_change' => false]);
        StudentInternshipPlacement::create(['student_internship_id' => $internship->id, 'hte_department_program_id' => $link->id, 'supervisor_record_id' => $assignment->id, 'is_current' => true]);

        return [$coordinator, $supervisor, $internship, $program, $section];
    }

    private function draft(User $coordinator): array
    {
        Sanctum::actingAs($coordinator);
        $form = $this->postJson('/api/coordinator/evaluation-forms', ['name' => 'BSIS rubric', 'academic_period_id' => $this->academicPeriod()->id])->assertCreated()->assertJsonPath('form.status', 'draft')->assertJsonPath('form.row_version', 0)->json('form');

        return $this->patchJson('/api/coordinator/evaluation-forms/'.$form['id'], [
            'row_version' => 0, 'rating_direction' => 'lower', 'allow_no_rating' => true,
            'rating_options' => [['numeric_value' => '1.000', 'label' => 'Excellent'], ['numeric_value' => '2.500', 'label' => 'Developing']],
            'computation_method' => 'rated_item_mean', 'computation_settings' => ['decimal_places' => 2, 'rounding' => 'half_up'],
            'categories' => [['title' => 'Knowledge', 'criteria' => [['label' => 'Understands procedures'], ['label' => 'Communicates']], 'subgroups' => []]],
        ])->assertOk()->json('form');
    }

    private function publish(array $form): array
    {
        return $this->postJson('/api/coordinator/evaluation-forms/'.$form['id'].'/publish', ['row_version' => $form['row_version']])->assertOk()->json('form');
    }

    public function test_complete_workflow_locks_grades_hides_drafts_and_is_idempotent(): void
    {
        [$coordinator, $supervisor, $internship] = $this->setupIntern();
        $form = $this->publish($this->draft($coordinator));
        Sanctum::actingAs($supervisor);
        $this->getJson('/api/supervisor/evaluation-students')->assertOk()->assertJsonPath('overview', ['assigned' => 1, 'submitted' => 0, 'remaining' => 1]);
        $evaluation = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->assertJsonPath('evaluation.row_version', 0)->json('evaluation');
        $id = $evaluation['id'];
        $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->assertJsonPath('evaluation.id', $id);
        $criteria = $form['categories'][0]['criteria'];
        $answer = ['criterion_id' => $criteria[0]['id'], 'rating_option_id' => $form['rating_options'][0]['id'], 'is_no_rating' => false];
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 0, 'responses' => [$answer], 'feedback' => 'Private draft'])->assertOk()->assertJsonPath('summary.answered', 1);
        $this->postJson("/api/supervisor/evaluations/$id/submit", ['row_version' => 1])->assertUnprocessable();
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 0, 'responses' => []])->assertConflict();
        Sanctum::actingAs($coordinator);
        $this->getJson('/api/evaluation-results')->assertOk()->assertDontSee('Private draft');
        $this->getJson('/api/evaluation-results/'.$id)->assertNotFound();
        Sanctum::actingAs($supervisor);
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 1, 'responses' => [['criterion_id' => $criteria[1]['id'], 'rating_option_id' => null, 'is_no_rating' => true]]])->assertOk()->assertJsonPath('summary.overall', '1.00')->assertJsonPath('summary.answered', 2);
        $this->getJson("/api/supervisor/evaluations/$id/review")->assertOk()->assertJsonPath('summary.can_submit', true);
        $submitted = $this->postJson("/api/supervisor/evaluations/$id/submit", ['row_version' => 2])->assertOk()->json('evaluation.submitted_at');
        $this->postJson("/api/supervisor/evaluations/$id/submit", ['row_version' => 2])->assertOk()->assertJsonPath('evaluation.submitted_at', $submitted);
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 3, 'responses' => []])->assertConflict();
        $this->getJson('/api/supervisor/evaluation-students')->assertOk()->assertJsonPath('overview.remaining', 0);
        Sanctum::actingAs($coordinator);
        $this->getJson('/api/evaluation-results/'.$id)->assertOk()->assertJsonPath('form.id', $form['id']);
        $this->assertDatabaseCount('evaluation_responses', 2);
        $this->assertDatabaseCount('evaluations', 1);
    }

    public function test_form_validation_versions_and_program_permissions(): void
    {
        [$coordinator, $supervisor, $internship, $program] = $this->setupIntern();
        $form = $this->draft($coordinator);
        $this->patchJson('/api/coordinator/evaluation-forms/'.$form['id'], ['row_version' => 1, 'rating_options' => [['numeric_value' => '1', 'label' => 'One'], ['numeric_value' => '1.00', 'label' => 'Duplicate']]])->assertUnprocessable();
        $this->patchJson('/api/coordinator/evaluation-forms/'.$form['id'], ['row_version' => 1, 'rating_options' => [['numeric_value' => '1.0001', 'label' => 'Too precise']]])->assertUnprocessable();
        $form = $this->publish($form);
        $this->patchJson('/api/coordinator/evaluation-forms/'.$form['id'], ['row_version' => 2, 'name' => 'Changed'])->assertConflict();
        Sanctum::actingAs($supervisor);
        $evaluation = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->json('evaluation');
        Sanctum::actingAs($coordinator);
        $copy = $this->postJson('/api/coordinator/evaluation-forms/'.$form['id'].'/duplicate')->assertCreated()->json('form');
        $this->publish($copy);
        $this->assertDatabaseHas('evaluation_forms', ['id' => $form['id'], 'status' => 'archived']);
        $this->assertDatabaseHas('evaluations', ['id' => $evaluation['id'], 'evaluation_form_id' => $form['id']]);
        Sanctum::actingAs($supervisor);
        $this->putJson('/api/supervisor/evaluations/'.$evaluation['id'].'/draft', ['row_version' => 0, 'responses' => [['criterion_id' => $form['categories'][0]['criteria'][0]['id'], 'rating_option_id' => $copy['rating_options'][0]['id'], 'is_no_rating' => false]]])->assertUnprocessable();
        $otherProgram = Program::create(['college_id' => $program->college_id, 'code' => 'BAPS', 'name' => 'Political Science', 'is_active' => true]);
        Sanctum::actingAs(User::factory()->create(['role' => 'coordinator', 'college_id' => $program->college_id, 'program_id' => $otherProgram->id]));
        $this->getJson('/api/coordinator/evaluation-forms/'.$form['id'])->assertNotFound();
        $this->getJson('/api/evaluation-results')->assertOk()->assertJsonCount(0, 'results.data');
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/evaluation-results')->assertForbidden();
    }

    public function test_missing_forms_all_no_rating_and_explicit_clear(): void
    {
        [$coordinator, $supervisor, $internship] = $this->setupIntern();
        Sanctum::actingAs($supervisor);
        $this->getJson('/api/supervisor/evaluation-students')->assertOk()->assertJsonPath('students.data.0.status', 'form_unavailable');
        $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertUnprocessable();
        $form = $this->publish($this->draft($coordinator));
        Sanctum::actingAs($supervisor);
        $id = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->json('evaluation.id');
        $responses = array_map(fn ($c) => ['criterion_id' => $c['id'], 'rating_option_id' => null, 'is_no_rating' => true], $form['categories'][0]['criteria']);
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 0, 'responses' => $responses])->assertOk()->assertJsonPath('summary.overall', null)->assertJsonPath('summary.can_submit', false);
        $this->postJson("/api/supervisor/evaluations/$id/submit", ['row_version' => 1])->assertUnprocessable();
        $responses[0]['is_no_rating'] = false;
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 1, 'responses' => [$responses[0]]])->assertOk()->assertJsonPath('summary.answered', 1);
        $this->assertDatabaseCount('evaluation_responses', 1);
    }

    public function test_csv_preview_requires_confirmation_owner_and_current_revision(): void
    {
        [$coordinator] = $this->setupIntern();
        $form = $this->draft($coordinator);
        $url = '/api/coordinator/evaluation-forms/'.$form['id'];
        $preview = $this->postJson($url.'/imports/preview', [
            'file' => UploadedFile::fake()->createWithContent('rubric.csv', "category,subgroup,criterion\nSkills,Programming,Can work in team\nSkills,Documentation,Can work in team\n"),
            'row_version' => 1, 'layout' => 'normalized', 'category_column' => 0, 'subgroup_column' => 1, 'criterion_column' => 2,
        ])->assertOk()->assertJsonPath('criteria_count', 2)->json();
        $this->assertDatabaseCount('evaluation_criteria', 2); // Preview made no changes.
        $this->postJson($url.'/imports/confirm', ['token' => $preview['token'], 'mode' => 'replace'])->assertOk()->assertJsonCount(2, 'form.categories.0.subgroups');
        $this->postJson($url.'/imports/confirm', ['token' => $preview['token'], 'mode' => 'replace'])->assertUnprocessable();
        $this->assertDatabaseCount('evaluation_criteria', 2);
    }

    public function test_report_layout_preserves_subgroups_and_repeated_criteria(): void
    {
        $file = UploadedFile::fake()->createWithContent('report.csv', "NOTE: Leave blank,,1,2\nPERFORMANCE FACTORS,,,\nI. KNOWLEDGE,1. Understands procedures,,\n,AVERAGE,,\nII. COMPETENCIES,1. Programming,,\n,a.) Can work in team.,,\n,2. Documentation,,\n,a.) Can work in team.,,\n,AVERAGE,,\nOVERALL AVERAGE,,,\nFINAL GRADE,,,\nSUBMITTED DOCUMENTS CHECKLIST:,,,\n1. Logs,,FALSE,FALSE\n");
        $preview = app(EvaluationCsvImport::class)->parse($file->getRealPath(), ['layout' => 'report', 'category_column' => 0, 'criterion_column' => 1]);
        $this->assertSame([], $preview['errors']);
        $this->assertSame(3, $preview['criteria_count']);
        $this->assertSame(2, $preview['subgroup_count']);
        $this->assertSame(2, $preview['category_count']);
    }

    public function test_actual_bsis_csv_imports_all_54_criteria_and_10_subgroups(): void
    {
        $preview = app(EvaluationCsvImport::class)->parse(base_path('tests/Fixtures/bsis-practicum-evaluation.csv'), ['layout' => 'report', 'category_column' => 0, 'criterion_column' => 1]);
        $this->assertSame([], $preview['errors']);
        $this->assertSame(54, $preview['criteria_count']);
        $this->assertSame(10, $preview['subgroup_count']);
        $this->assertSame(5, $preview['category_count']);
        $this->assertSame('Programming language', $preview['categories'][1]['subgroups'][1]['criteria'][0]['extra_input_label']);
        $this->assertSame('Software used', $preview['categories'][1]['subgroups'][7]['criteria'][0]['extra_input_label']);
    }

    public function test_category_mean_uses_unrounded_category_values_and_enforces_empty_category_policy(): void
    {
        [$coordinator, $supervisor, $internship] = $this->setupIntern();
        $form = $this->draft($coordinator);
        $form = $this->patchJson('/api/coordinator/evaluation-forms/'.$form['id'], [
            'row_version' => 1, 'rating_direction' => 'higher', 'computation_method' => 'category_mean',
            'computation_settings' => ['decimal_places' => 2, 'rounding' => 'half_up', 'empty_category' => 'require_numeric'],
            'categories' => [
                ['title' => 'Large category', 'criteria' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']], 'subgroups' => []],
                ['title' => 'Small category', 'criteria' => [['label' => 'D']], 'subgroups' => []],
            ],
        ])->assertOk()->json('form');
        $form = $this->publish($form);
        Sanctum::actingAs($supervisor);
        $id = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->json('evaluation.id');
        $responses = array_map(fn ($c) => ['criterion_id' => $c['id'], 'rating_option_id' => $form['rating_options'][0]['id'], 'is_no_rating' => false], $form['categories'][0]['criteria']);
        $responses[] = ['criterion_id' => $form['categories'][1]['criteria'][0]['id'], 'rating_option_id' => null, 'is_no_rating' => true];
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 0, 'responses' => $responses])->assertOk()->assertJsonPath('summary.can_submit', false)->assertJsonPath('summary.categories.1.mean', null);
        $responses[3]['is_no_rating'] = false;
        $responses[3]['rating_option_id'] = $form['rating_options'][1]['id'];
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 1, 'responses' => [$responses[3]]])->assertOk()->assertJsonPath('summary.overall', '1.75')->assertJsonPath('summary.can_submit', true)->assertJsonPath('summary.rating_direction', 'higher');
    }

    public function test_results_roles_scopes_and_database_retention(): void
    {
        [$coordinator, $supervisor, $internship, $program, $section] = $this->setupIntern();
        $form = $this->publish($this->draft($coordinator));
        Sanctum::actingAs($supervisor);
        $id = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->json('evaluation.id');
        $responses = array_map(fn ($c) => ['criterion_id' => $c['id'], 'rating_option_id' => $form['rating_options'][0]['id'], 'is_no_rating' => false], $form['categories'][0]['criteria']);
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 0, 'responses' => $responses])->assertOk();
        $this->postJson("/api/supervisor/evaluations/$id/submit", ['row_version' => 1])->assertOk();
        foreach (['program_head', 'dean'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'college_id' => $program->college_id, 'program_id' => $role === 'program_head' ? $program->id : null]));
            $this->getJson('/api/evaluation-results/'.$id)->assertOk();
            $this->getJson('/api/evaluation-results')->assertOk()->assertJsonCount(1, 'results.data');
        }
        $otherCollege = College::create(['name' => 'Other College', 'code' => 'OTHER', 'is_active' => true]);
        Sanctum::actingAs(User::factory()->create(['role' => 'dean', 'college_id' => $otherCollege->id]));
        $this->getJson('/api/evaluation-results/'.$id)->assertNotFound();
        $this->getJson('/api/evaluation-results')->assertOk()->assertJsonCount(0, 'results.data');
        Sanctum::actingAs(User::factory()->create(['role' => 'coordinator', 'college_id' => $program->college_id, 'program_id' => $program->id]));
        $this->getJson('/api/coordinator/evaluation-forms/'.$form['id'])->assertOk(); // Form is program-wide.
        $this->getJson('/api/evaluation-results/'.$id)->assertNotFound(); // Results are section-scoped.
        $this->expectException(QueryException::class);
        DB::table('students')->where('id', $internship->student_id)->delete();
    }

    public function test_import_tokens_are_owner_bound_and_stale_imports_do_not_change_rubrics(): void
    {
        [$coordinator, , , $program] = $this->setupIntern();
        $form = $this->draft($coordinator);
        $url = '/api/coordinator/evaluation-forms/'.$form['id'];
        $preview = $this->postJson($url.'/imports/preview', ['file' => UploadedFile::fake()->createWithContent('x.csv', "category,subgroup,criterion\nSkills,,First criterion\n"), 'row_version' => 1, 'layout' => 'normalized', 'category_column' => 0, 'criterion_column' => 2])->assertOk()->json();
        Sanctum::actingAs(User::factory()->create(['role' => 'coordinator', 'program_id' => $program->id, 'college_id' => $program->college_id]));
        $this->postJson($url.'/imports/confirm', ['token' => $preview['token'], 'mode' => 'replace'])->assertUnprocessable();
        Sanctum::actingAs($coordinator);
        $this->patchJson($url, ['row_version' => 1, 'name' => 'Renamed'])->assertOk();
        $this->postJson($url.'/imports/confirm', ['token' => $preview['token'], 'mode' => 'replace'])->assertConflict();
        $this->assertDatabaseCount('evaluation_criteria', 2);
    }

    public function test_ratings_only_no_rating_disabled_and_unassigned_supervisor_are_enforced(): void
    {
        [$coordinator, $supervisor, $internship] = $this->setupIntern();
        $form = $this->draft($coordinator);
        $form = $this->patchJson('/api/coordinator/evaluation-forms/'.$form['id'], ['row_version' => 1, 'computation_method' => 'ratings_only', 'allow_no_rating' => false])->assertOk()->json('form');
        $form = $this->publish($form);
        Sanctum::actingAs($supervisor);
        $id = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->json('evaluation.id');
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 0, 'responses' => [['criterion_id' => $form['categories'][0]['criteria'][0]['id'], 'rating_option_id' => null, 'is_no_rating' => true]]])->assertUnprocessable();
        $responses = array_map(fn ($c) => ['criterion_id' => $c['id'], 'rating_option_id' => $form['rating_options'][0]['id'], 'is_no_rating' => false], $form['categories'][0]['criteria']);
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 0, 'responses' => $responses])->assertOk()->assertJsonPath('summary.overall', null)->assertJsonPath('summary.can_submit', true);
        $unassigned = User::factory()->create(['role' => 'supervisor']);
        Sanctum::actingAs($unassigned);
        $this->getJson("/api/supervisor/evaluations/$id")->assertNotFound();
        $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertNotFound();
    }

    public function test_same_student_has_separate_internship_evaluations_and_historical_form_selection(): void
    {
        [$coordinator, $supervisor, $internship, , $section] = $this->setupIntern();
        $form = $this->publish($this->draft($coordinator));
        Sanctum::actingAs($supervisor);
        $first = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->json('evaluation.id');
        $oldSection = AcademicSection::create(['program_id' => $section->program_id, 'academic_period_id' => $this->academicPeriod('2025-2026')->id, 'name' => 'Historical BSIS', 'year_level' => 4, 'is_active' => false]);
        $older = StudentInternship::create(['student_id' => $internship->student_id, 'academic_section_id' => $oldSection->id, 'status' => 'completed', 'is_current' => false]);
        $placement = $internship->currentPlacement;
        StudentInternshipPlacement::create(['student_internship_id' => $older->id, 'hte_department_program_id' => $placement->hte_department_program_id, 'supervisor_record_id' => $placement->supervisor_record_id, 'is_current' => true]);
        $this->postJson('/api/supervisor/internships/'.$older->id.'/evaluation/start')->assertUnprocessable(); // Must not use the current-period form.
        Sanctum::actingAs($coordinator);
        $copy = $this->postJson('/api/coordinator/evaluation-forms/'.$form['id'].'/duplicate', ['academic_period_id' => $oldSection->academic_period_id])->assertCreated()->json('form');
        $this->publish($copy);
        Sanctum::actingAs($supervisor);
        $second = $this->postJson('/api/supervisor/internships/'.$older->id.'/evaluation/start')->assertOk()->assertJsonPath('evaluation.evaluation_form_id', $copy['id'])->json('evaluation.id');
        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('evaluations', 2);
    }

    public function test_decimal_mean_rounds_half_up_without_binary_float_error(): void
    {
        [$coordinator, $supervisor, $internship] = $this->setupIntern();
        $form = $this->draft($coordinator);
        $form = $this->patchJson('/api/coordinator/evaluation-forms/'.$form['id'], ['row_version' => 1, 'rating_options' => [['numeric_value' => '1.004', 'label' => 'First'], ['numeric_value' => '1.006', 'label' => 'Second']]])->assertOk()->json('form');
        $form = $this->publish($form);
        Sanctum::actingAs($supervisor);
        $id = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->json('evaluation.id');
        $responses = [];
        foreach ($form['categories'][0]['criteria'] as $i => $criterion) {
            $responses[] = ['criterion_id' => $criterion['id'], 'rating_option_id' => $form['rating_options'][$i]['id'], 'is_no_rating' => false];
        }
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 0, 'responses' => $responses])->assertOk()->assertJsonPath('summary.overall', '1.01');
    }

    public function test_database_rejects_cross_form_options_and_legacy_records_remain_readable(): void
    {
        [$coordinator, $supervisor, $internship] = $this->setupIntern();
        $form = $this->publish($this->draft($coordinator));
        $copy = $this->postJson('/api/coordinator/evaluation-forms/'.$form['id'].'/duplicate')->assertCreated()->json('form');
        Sanctum::actingAs($supervisor);
        $id = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->json('evaluation.id');
        $legacy = Evaluation::create(['student_id' => $internship->student_id, 'student_internship_id' => $internship->id, 'supervisor_id' => $supervisor->id, 'evaluation_type' => 'midterm', 'work_quality' => 4, 'communication' => 4, 'professionalism' => 4, 'attendance' => 4, 'technical_skills' => 4, 'teamwork' => 4, 'status' => 'submitted', 'submitted_at' => now()]);
        $this->getJson('/api/supervisor/evaluations/'.$legacy->id)->assertOk()->assertJsonPath('legacy', true)->assertJsonPath('evaluation.work_quality', 4);
        $this->postJson('/api/supervisor/evaluations', ['evaluation_type' => 'midterm'])->assertConflict();
        Sanctum::actingAs($coordinator);
        $this->getJson('/api/evaluation-results/'.$legacy->id)->assertOk()->assertJsonPath('legacy', true);
        $this->expectException(QueryException::class);
        DB::table('evaluation_responses')->insert(['evaluation_id' => $id, 'evaluation_form_id' => $form['id'], 'evaluation_criterion_id' => $form['categories'][0]['criteria'][0]['id'], 'evaluation_rating_option_id' => $copy['rating_options'][0]['id'], 'is_no_rating' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_delete_draft_removes_its_entire_hierarchy_and_rating_options(): void
    {
        [$coordinator] = $this->setupIntern();
        $form = $this->draft($coordinator);
        $url = '/api/coordinator/evaluation-forms/'.$form['id'];
        $form = $this->patchJson($url, ['row_version' => 1, 'categories' => [
            ['title' => 'Knowledge', 'criteria' => [['label' => 'Direct criterion']], 'subgroups' => [
                ['title' => 'Programming', 'criteria' => [['label' => 'Writes code']]],
            ]],
        ]])->assertOk()->json('form');
        $this->deleteJson($url, ['row_version' => $form['row_version']])->assertOk()->assertJsonPath('message', 'Evaluation draft deleted.');
        foreach (['evaluation_forms', 'evaluation_categories', 'evaluation_subgroups', 'evaluation_criteria', 'evaluation_rating_options'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->getJson($url)->assertNotFound();
        $this->getJson('/api/coordinator/evaluation-forms')->assertOk()->assertJsonCount(0, 'forms.data');
    }

    public function test_delete_draft_requires_current_revision_and_authorized_program(): void
    {
        [$coordinator, $supervisor, , $program] = $this->setupIntern();
        $form = $this->draft($coordinator);
        $url = '/api/coordinator/evaluation-forms/'.$form['id'];
        $this->deleteJson($url)->assertUnprocessable();
        $this->deleteJson($url, ['row_version' => 0])->assertConflict();
        $this->assertDatabaseCount('evaluation_criteria', 2);
        $other = Program::create(['college_id' => $program->college_id, 'code' => 'OTHER', 'name' => 'Other program', 'is_active' => true]);
        Sanctum::actingAs(User::factory()->create(['role' => 'coordinator', 'college_id' => $program->college_id, 'program_id' => $other->id]));
        $this->deleteJson($url, ['row_version' => 1])->assertNotFound();
        Sanctum::actingAs($supervisor);
        $this->deleteJson($url, ['row_version' => 1])->assertForbidden();
        Sanctum::actingAs($coordinator);
        $this->assertDatabaseCount('evaluation_forms', 1);
        $this->deleteJson($url, ['row_version' => 1])->assertOk();
    }

    public function test_delete_preserves_published_archived_forms_and_submitted_results(): void
    {
        [$coordinator, $supervisor, $internship] = $this->setupIntern();
        $form = $this->publish($this->draft($coordinator));
        $url = '/api/coordinator/evaluation-forms/'.$form['id'];
        $this->deleteJson($url, ['row_version' => $form['row_version']])->assertConflict();
        Sanctum::actingAs($supervisor);
        $id = $this->postJson('/api/supervisor/internships/'.$internship->id.'/evaluation/start')->assertOk()->json('evaluation.id');
        $responses = array_map(fn ($c) => ['criterion_id' => $c['id'], 'rating_option_id' => $form['rating_options'][0]['id'], 'is_no_rating' => false], $form['categories'][0]['criteria']);
        $this->putJson("/api/supervisor/evaluations/$id/draft", ['row_version' => 0, 'responses' => $responses])->assertOk();
        $this->postJson("/api/supervisor/evaluations/$id/submit", ['row_version' => 1])->assertOk();
        Sanctum::actingAs($coordinator);
        $this->postJson($url.'/archive', ['row_version' => $form['row_version']])->assertOk();
        $this->deleteJson($url, ['row_version' => $form['row_version'] + 1])->assertConflict();
        $this->getJson('/api/evaluation-results/'.$id)->assertOk()->assertJsonPath('evaluation.status', 'submitted');
        $this->assertDatabaseCount('evaluation_responses', 2);
        $this->assertDatabaseCount('evaluation_rating_options', 2);
    }

    public function test_delete_rejects_any_referenced_draft_and_can_remove_an_empty_draft(): void
    {
        [$coordinator, $supervisor, $internship] = $this->setupIntern();
        $form = $this->draft($coordinator);
        Evaluation::create(['student_id' => $internship->student_id, 'student_internship_id' => $internship->id, 'supervisor_id' => $supervisor->id, 'evaluation_type' => 'whole_semester', 'evaluation_form_id' => $form['id'], 'status' => 'draft']);
        $this->deleteJson('/api/coordinator/evaluation-forms/'.$form['id'], ['row_version' => 1])->assertConflict();
        $this->assertDatabaseCount('evaluation_criteria', 2);
        $empty = $this->postJson('/api/coordinator/evaluation-forms', ['name' => 'Empty draft', 'academic_period_id' => $this->academicPeriod()->id])->assertCreated()->json('form');
        $this->deleteJson('/api/coordinator/evaluation-forms/'.$empty['id'], ['row_version' => 0])->assertOk();
        $this->assertDatabaseCount('evaluation_forms', 1);
        $this->assertDatabaseCount('evaluations', 1);
    }
}
