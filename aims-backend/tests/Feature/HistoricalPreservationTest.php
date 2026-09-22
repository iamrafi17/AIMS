<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\College;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HistoricalPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_delete_request_deactivates_a_user_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'supervisor', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$user->id}")->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_admin_can_delete_unconnected_academic_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'BSIT',
            'is_active' => true,
        ]);
        $section = AcademicSection::create([
            'program_id' => $program->id,
            'academic_period_id' => $this->academicPeriod()->id,
            'name' => 'A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/academic/sections/{$section->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Section deleted successfully.');
        $this->deleteJson("/api/admin/academic/programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Program deleted successfully.');
        $this->deleteJson("/api/admin/academic/colleges/{$college->id}")
            ->assertOk()
            ->assertJsonPath('message', 'College deleted successfully.');

        $this->assertDatabaseMissing('academic_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
        $this->assertDatabaseMissing('colleges', ['id' => $college->id]);
    }

    public function test_academic_delete_is_blocked_when_connected_records_exist(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'BSIT',
            'is_active' => true,
        ]);
        $section = AcademicSection::create([
            'program_id' => $program->id,
            'academic_period_id' => $this->academicPeriod()->id,
            'name' => 'A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/academic/colleges/{$college->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Computing has connected records and cannot be deleted.');
        $this->deleteJson("/api/admin/academic/programs/{$program->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Information Technology has connected records and cannot be deleted.');
        $this->deleteJson("/api/admin/academic/sections/{$section->id}")->assertOk();

        $this->assertDatabaseHas('colleges', ['id' => $college->id]);
        $this->assertDatabaseHas('programs', ['id' => $program->id]);
    }
}
