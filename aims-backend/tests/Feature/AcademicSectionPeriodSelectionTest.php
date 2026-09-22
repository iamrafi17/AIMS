<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\College;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicSectionPeriodSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_api_supplies_period_options_and_sections_show_their_selected_semester(): void
    {
        [$program, $first, $second] = $this->context();
        $this->getJson('/api/admin/academic')->assertOk()
            ->assertJsonCount(2, 'academic_periods')
            ->assertJsonPath('academic_periods.0.id', $second->id)
            ->assertJsonPath('academic_periods.0.semester', '2nd Semester');

        $id = $this->postJson('/api/admin/academic/sections', $this->payload($program, $first))
            ->assertCreated()->assertJsonPath('section.academic_period_id', $first->id)->json('section.id');
        $this->getJson('/api/admin/academic')->assertOk()
            ->assertJsonPath('sections.0.id', $id)
            ->assertJsonPath('sections.0.academic_period.semester', '1st Semester');
    }

    public function test_selected_period_determines_year_and_link_even_when_another_period_is_active(): void
    {
        [$program, $first] = $this->context();
        $this->postJson('/api/admin/academic/sections', [
            ...$this->payload($program, $first), 'academic_year' => '2030-2031',
        ])->assertCreated()->assertJsonPath('section.academic_period_id', $first->id)
            ->assertJsonPath('section.academic_year', '2026-2027');
    }

    public function test_missing_or_unknown_period_is_rejected_without_creating_a_section(): void
    {
        [$program, $first] = $this->context();
        $payload = $this->payload($program, $first);
        unset($payload['academic_period_id']);
        $this->postJson('/api/admin/academic/sections', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('academic_period_id');
        $this->postJson('/api/admin/academic/sections', [...$payload, 'academic_period_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseCount('academic_sections', 0);
    }

    public function test_same_section_name_can_be_used_in_different_semesters_but_not_twice_in_one_period(): void
    {
        [$program, $first, $second] = $this->context();
        $this->postJson('/api/admin/academic/sections', $this->payload($program, $first))->assertCreated();
        $this->postJson('/api/admin/academic/sections', $this->payload($program, $second))->assertCreated();
        $this->postJson('/api/admin/academic/sections', $this->payload($program, $second))->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('academic_sections', 2);
    }

    public function test_unlinked_section_can_change_period_and_inherit_the_new_year(): void
    {
        [$program, $first] = $this->context();
        $next = AcademicPeriod::create([
            'academic_year' => '2027-2028', 'semester' => '1st Semester',
            'start_date' => '2027-08-01', 'end_date' => '2027-12-01', 'status' => 'draft',
        ]);
        $id = $this->postJson('/api/admin/academic/sections', $this->payload($program, $first))->assertCreated()->json('section.id');
        $this->putJson('/api/admin/academic/sections/'.$id, $this->payload($program, $next))->assertOk()
            ->assertJsonPath('section.academic_period_id', $next->id)->assertJsonPath('section.academic_year', '2027-2028');
    }

    public function test_section_with_enrolled_students_keeps_its_period_but_details_can_be_corrected(): void
    {
        [$program, $first, $second] = $this->context();
        $id = $this->postJson('/api/admin/academic/sections', $this->payload($program, $first))->assertCreated()->json('section.id');
        OjtEnrollment::create([
            'school_id' => 'PERIOD-SECTION-001', 'full_name' => 'Enrolled Student', 'section' => '4A',
            'academic_section_id' => $id, 'program_id' => $program->id, 'college_id' => $program->college_id, 'source' => 'manual',
        ]);
        $this->putJson('/api/admin/academic/sections/'.$id, $this->payload($program, $second))->assertUnprocessable();
        $this->assertDatabaseHas('academic_sections', ['id' => $id, 'academic_period_id' => $first->id]);
        $this->putJson('/api/admin/academic/sections/'.$id, [...$this->payload($program, $first), 'name' => 'Section 4A'])
            ->assertOk()->assertJsonPath('section.name', 'Section 4A');
    }

    private function context(): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $college = College::create(['name' => 'Computing', 'code' => 'CICS']);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Systems', 'code' => 'BSIS']);
        $first = AcademicPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => '2026-08-01', 'end_date' => '2026-12-01', 'status' => 'draft',
        ]);
        $second = AcademicPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '2nd Semester',
            'start_date' => '2026-12-15', 'end_date' => '2027-04-25', 'status' => 'active',
        ]);

        return [$program, $first, $second];
    }

    private function payload(Program $program, AcademicPeriod $period): array
    {
        return ['program_id' => $program->id, 'name' => '4A', 'year_level' => 4, 'academic_period_id' => $period->id];
    }
}
