<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAcademicCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_read_update_and_delete_academic_records(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '2nd Semester',
            'start_date' => '2026-12-15', 'end_date' => '2027-04-25', 'status' => 'active',
        ]);

        $collegeId = $this->postJson('/api/admin/academic/colleges', [
            'name' => 'College of Computing',
            'code' => 'CCS',
            'required_ojt_hours' => 486,
        ])->assertCreated()
            ->assertJsonPath('message', 'College created successfully.')
            ->json('college.id');

        $programId = $this->postJson('/api/admin/academic/programs', [
            'college_id' => $collegeId,
            'name' => 'Information Technology',
            'code' => 'BSIT',
        ])->assertCreated()
            ->assertJsonPath('message', 'Program created successfully.')
            ->json('program.id');

        $sectionId = $this->postJson('/api/admin/academic/sections', [
            'program_id' => $programId,
            'name' => 'Section A',
            'year_level' => 4,
            'academic_period_id' => $period->id,
        ])->assertCreated()
            ->assertJsonPath('message', 'Section created successfully.')
            ->json('section.id');

        $this->getJson('/api/admin/academic')
            ->assertOk()
            ->assertJsonPath('overview.colleges', 1)
            ->assertJsonPath('overview.programs', 1)
            ->assertJsonPath('overview.sections', 1)
            ->assertJsonPath('colleges.0.id', $collegeId)
            ->assertJsonPath('programs.0.id', $programId)
            ->assertJsonPath('sections.0.id', $sectionId);

        $this->putJson("/api/admin/academic/colleges/{$collegeId}", [
            'name' => 'College of Computing Sciences',
            'code' => 'CCS',
            'required_ojt_hours' => 500,
        ])->assertOk()
            ->assertJsonPath('college.name', 'College of Computing Sciences');

        $this->putJson("/api/admin/academic/programs/{$programId}", [
            'college_id' => $collegeId,
            'name' => 'Bachelor of Science in Information Technology',
            'code' => 'BSIT',
        ])->assertOk()
            ->assertJsonPath('program.name', 'Bachelor of Science in Information Technology');

        $this->putJson("/api/admin/academic/sections/{$sectionId}", [
            'program_id' => $programId,
            'name' => 'Section Alpha',
            'year_level' => 4,
            'academic_period_id' => $period->id,
        ])->assertOk()
            ->assertJsonPath('section.name', 'Section Alpha');

        $this->deleteJson("/api/admin/academic/sections/{$sectionId}")->assertOk();
        $this->deleteJson("/api/admin/academic/programs/{$programId}")->assertOk();
        $this->deleteJson("/api/admin/academic/colleges/{$collegeId}")->assertOk();

        $this->assertDatabaseMissing('academic_sections', ['id' => $sectionId]);
        $this->assertDatabaseMissing('programs', ['id' => $programId]);
        $this->assertDatabaseMissing('colleges', ['id' => $collegeId]);
    }
}
