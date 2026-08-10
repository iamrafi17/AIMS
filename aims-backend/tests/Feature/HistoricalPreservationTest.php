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

    public function test_admin_delete_requests_deactivate_users_and_academic_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'supervisor', 'is_active' => true]);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'BSIT',
            'is_active' => true,
        ]);
        $section = AcademicSection::create([
            'program_id' => $program->id,
            'name' => 'A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$user->id}")->assertOk();
        $this->deleteJson("/api/admin/academic/sections/{$section->id}")->assertOk();
        $this->deleteJson("/api/admin/academic/programs/{$program->id}")->assertOk();
        $this->deleteJson("/api/admin/academic/colleges/{$college->id}")->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->assertDatabaseHas('academic_sections', ['id' => $section->id, 'is_active' => false]);
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'is_active' => false]);
        $this->assertDatabaseHas('colleges', ['id' => $college->id, 'is_active' => false]);
    }
}
