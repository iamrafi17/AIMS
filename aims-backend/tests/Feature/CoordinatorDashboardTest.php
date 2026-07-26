<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\Program;
use App\Models\Student;
use App\Models\TravelLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_dashboard_returns_operational_summaries(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $studentUser = User::factory()->create(['role' => 'student']);
        $college = College::create(['name' => 'Computing', 'code' => 'CC', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $coordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        $hte = HTE::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => 'Partner Company',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_phone' => '09170000000',
            'is_active' => true,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-0001',
            'first_name' => 'Test',
            'last_name' => 'Intern',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'phone' => '09171111111',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_phone' => '09172222222',
            'hte_id' => $hte->id,
            'registration_status' => 'approved',
            'internship_status' => 'active',
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
            'status' => 'present',
            'am_time_in' => now()->setTime(8, 0),
            'am_time_out' => now()->setTime(12, 0),
            'pm_time_in' => now()->setTime(13, 0),
            'pm_time_out' => now()->setTime(17, 0),
            'am_activity' => 'Morning accomplishments',
            'pm_activity' => 'Afternoon accomplishments',
        ]);

        InternshipRequirement::create([
            'student_id' => $student->id,
            'requirement_name' => 'Consent Form',
            'file_path' => 'requirements/consent.pdf',
            'file_type' => 'pdf',
            'status' => 'approved',
        ]);

        MOA::create([
            'hte_id' => $hte->id,
            'college_id' => $college->id,
            'program_id' => $program->id,
            'file_path' => 'moas/partner.pdf',
            'effective_date' => now()->subMonth(),
            'expiration_date' => now()->addYear(),
            'status' => 'approved',
        ]);

        TravelLog::create([
            'student_id' => $student->id,
            'session_code' => 'TRAVEL-001',
            'start_time' => now(),
            'status' => 'active',
        ]);

        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/dashboard')
            ->assertOk()
            ->assertJsonPath('overview.total_students', 1)
            ->assertJsonPath('overview.active', 1)
            ->assertJsonPath('attendance.present', 1)
            ->assertJsonPath('attendance.rate', 100)
            ->assertJsonPath('attendance.total_hours', 8)
            ->assertJsonPath('journals.submitted', 2)
            ->assertJsonPath('journals.completion_rate', 100)
            ->assertJsonPath('requirements.approved', 1)
            ->assertJsonPath('requirements.completion_rate', 100)
            ->assertJsonPath('hte_moa.active_htes', 1)
            ->assertJsonPath('hte_moa.moa_approved', 1)
            ->assertJsonPath('travel.active', 1)
            ->assertJsonPath('active_interns.0.student_id', '2026-0001')
            ->assertJsonPath('active_interns.0.progress_percent', 1.6);
    }
}
