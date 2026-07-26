<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorAttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_list_filter_and_analyze_attendance_and_journals(): void
    {
        [$coordinator, $student] = $this->context();
        Attendance::create([
            'student_id' => $student->id,
            'date' => '2026-07-20',
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
            'am_time_in' => '2026-07-20 08:00:00',
            'am_time_out' => '2026-07-20 12:00:00',
            'pm_time_in' => '2026-07-20 13:00:00',
            'pm_time_out' => '2026-07-20 17:00:00',
            'am_activity' => 'Prepared the daily system report.',
            'pm_activity' => 'Updated internship records.',
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'date' => '2026-07-21',
            'status' => 'absent',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
        ]);
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/attendance?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.present', 1)
            ->assertJsonPath('summary.absent', 1)
            ->assertJsonPath('summary.total_hours', 8)
            ->assertJsonPath('summary.journals_submitted', 1)
            ->assertJsonPath('analytics.status_breakdown.0.name', 'Present')
            ->assertJsonPath('analytics.status_breakdown.0.value', 1)
            ->assertJsonPath('analytics.work_modes.0.name', 'WFO')
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/coordinator/attendance?status=present&journal=submitted')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.journal_complete', true)
            ->assertJsonPath('data.0.total_hours', 8);
    }

    public function test_coordinator_can_verify_edit_and_review_a_daily_journal(): void
    {
        [$coordinator, $student] = $this->context();
        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => '2026-07-20',
            'status' => 'present',
            'work_mode' => 'wfh',
            'session_type' => 'am_half',
            'am_time_in' => '2026-07-20 08:00:00',
            'am_time_out' => '2026-07-20 12:00:00',
            'am_activity' => 'Completed the assigned documentation.',
        ]);
        Sanctum::actingAs($coordinator);

        $this->putJson("/api/coordinator/attendance/{$attendance->id}/verify", ['verified' => true])
            ->assertOk()
            ->assertJsonPath('attendance.is_verified', true);

        $this->postJson("/api/coordinator/attendance/{$attendance->id}/journal-review", [
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('attendance.journal_status', 'approved');

        $this->assertDatabaseHas('attendance', [
            'id' => $attendance->id,
            'is_verified' => true,
            'verified_by' => $coordinator->id,
            'journal_status' => 'approved',
            'journal_reviewed_by' => $coordinator->id,
        ]);

        $this->putJson("/api/coordinator/attendance/{$attendance->id}", [
            'status' => 'present',
            'work_mode' => 'wfh',
            'session_type' => 'am_half',
            'am_time_in' => '2026-07-20 08:00:00',
            'am_time_out' => '2026-07-20 12:00:00',
            'pm_time_in' => null,
            'pm_time_out' => null,
            'ot_start' => null,
            'ot_end' => null,
            'overtime_hours' => 0,
            'am_activity' => 'Corrected and expanded accomplishment report.',
            'pm_activity' => null,
        ])->assertOk()
            ->assertJsonPath('attendance.is_verified', false)
            ->assertJsonPath('attendance.journal_status', 'pending');

        $this->assertDatabaseHas('attendance', [
            'id' => $attendance->id,
            'is_verified' => false,
            'verified_by' => null,
            'journal_status' => 'pending',
            'journal_reviewed_by' => null,
        ]);
    }

    private function context(): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $studentUser = User::factory()->create(['role' => 'student']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
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
            'student_id' => '2026-3001',
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

        return [$coordinator, $student];
    }
}
