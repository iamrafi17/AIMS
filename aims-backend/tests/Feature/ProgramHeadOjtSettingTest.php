<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramHeadOjtSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_head_publishes_only_for_assigned_program_and_can_update_before_deployment(): void
    {
        [$head, $coordinator, $program, $period] = $this->context();
        Sanctum::actingAs($head);

        $this->getJson('/api/program-head/ojt-settings')->assertOk()
            ->assertJsonPath('program.id', $program->id);

        $payload = ['academic_period_id' => $period->id, 'required_hours' => 486, 'ojt_start_date' => '2027-01-15'];
        $this->postJson('/api/program-head/ojt-settings/publish', $payload)->assertOk()
            ->assertJsonPath('setting.status', 'published')
            ->assertJsonPath('setting.program_id', $program->id);
        $this->postJson('/api/program-head/ojt-settings/publish', [...$payload, 'required_hours' => 500])->assertOk();
        $this->assertDatabaseCount('program_ojt_settings', 1);
        $this->assertDatabaseHas('program_ojt_settings', [
            'program_id' => $program->id, 'academic_period_id' => $period->id, 'required_hours' => 500,
        ]);

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/program-head/ojt-settings')->assertForbidden();
        $this->getJson('/api/coordinator/htes')->assertOk()
            ->assertJsonPath('ojt_settings.0.required_hours', '500.00');
    }

    public function test_published_values_override_coordinator_deployment_and_cannot_change_after_use(): void
    {
        [$head, $coordinator, $program, $period, $section] = $this->context();
        $hte = HTE::create([
            'name' => 'OJT Partner', 'address' => 'Santa Cruz', 'contact_person' => 'Supervisor',
            'contact_email' => 'partner@example.test', 'contact_number' => '09170000000',
            'geofence_radius' => 100, 'geofence_enabled' => false,
            'default_am_start' => '08:00', 'default_am_end' => '12:00',
            'default_pm_start' => '13:00', 'default_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'is_active' => true,
        ]);
        DB::table('hte_programs')->insert([
            'hte_id' => $hte->id, 'program_id' => $program->id, 'linked_by' => $coordinator->id,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentUser = User::factory()->create(['role' => 'student']);
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_id' => '2026-9001', 'first_name' => 'Test',
            'last_name' => 'Intern', 'gender' => 'female', 'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz', 'contact_number' => '09171111111',
            'college_id' => $program->college_id, 'program_id' => $program->id,
            'academic_section_id' => $section->id, 'year_level' => 4, 'section' => 'A',
            'parent_name' => 'Parent', 'parent_address' => 'Santa Cruz',
            'parent_contact_number' => '09172222222', 'registration_status' => 'approved',
            'internship_status' => 'pending',
        ]);

        Sanctum::actingAs($head);
        $setting = ['academic_period_id' => $period->id, 'required_hours' => 486, 'ojt_start_date' => '2027-01-15'];
        $this->postJson('/api/program-head/ojt-settings/publish', $setting)->assertOk();

        Sanctum::actingAs($coordinator);
        $deployment = [
            'hte_id' => $hte->id, 'required_ojt_hours' => 999,
            'ojt_start_date' => '2026-08-01', 'ojt_end_date' => '2027-05-15',
            'official_am_start' => '08:00', 'official_am_end' => '12:00',
            'official_pm_start' => '13:00', 'official_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'internship_status' => 'active', 'allow_past_attendance' => false,
        ];
        $this->putJson("/api/coordinator/htes/deployments/{$student->id}", $deployment)->assertOk();
        $this->assertSame(486.0, (float) $student->fresh()->required_ojt_hours);
        $this->assertSame('2027-01-15', $student->fresh()->ojt_start_date->toDateString());
        $this->assertNull($student->fresh()->ojt_end_date);

        Sanctum::actingAs($head);
        $this->postJson('/api/program-head/ojt-settings/publish', [
            ...$setting, 'required_hours' => 500,
        ])->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseHas('program_ojt_settings', [
            'program_id' => $program->id, 'academic_period_id' => $period->id, 'required_hours' => 486,
        ]);
    }

    public function test_completed_period_cannot_be_published(): void
    {
        [$head, , , $period] = $this->context();
        $period->update(['status' => 'completed']);
        Sanctum::actingAs($head);
        $this->postJson('/api/program-head/ojt-settings/publish', [
            'academic_period_id' => $period->id, 'required_hours' => 486,
            'ojt_start_date' => '2027-01-15',
        ])->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
    }

    public function test_registration_preview_uses_published_hours_and_admin_college_form_no_longer_needs_hours(): void
    {
        [$head, $coordinator, $program, $period, $section] = $this->context();
        $program->college->update(['required_ojt_hours' => 600]);
        OjtEnrollment::create([
            'school_id' => '2026-SETUP-001', 'full_name' => 'SANTOS, Maria',
            'section' => 'BSIS 4A', 'college_id' => $program->college_id,
            'program_id' => $program->id, 'academic_section_id' => $section->id,
            'source' => 'manual', 'added_by' => $coordinator->id,
        ]);

        $this->getJson('/api/registration/enrollment/2026-SETUP-001')->assertOk()
            ->assertJsonPath('required_ojt_hours', 600);
        Sanctum::actingAs($head);
        $this->postJson('/api/program-head/ojt-settings/publish', [
            'academic_period_id' => $period->id, 'required_hours' => 486,
            'ojt_start_date' => '2027-01-15',
        ])->assertOk();
        $this->getJson('/api/registration/enrollment/2026-SETUP-001')->assertOk()
            ->assertJsonPath('required_ojt_hours', 486);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/admin/academic/colleges', [
            'name' => 'New College', 'code' => 'NEW',
        ])->assertCreated();
        $this->putJson("/api/admin/academic/colleges/{$program->college_id}", [
            'name' => 'Computing Sciences', 'code' => 'CICS',
        ])->assertOk();
        $this->assertDatabaseHas('colleges', ['id' => $program->college_id, 'required_ojt_hours' => 600]);
    }

    public function test_absence_extends_estimate_and_completed_attendance_counts_without_review(): void
    {
        Carbon::setTestNow('2027-01-18 09:00:00');
        try {
            [$head, $coordinator, $program, $period, $section] = $this->context();
            $hte = HTE::create([
                'name' => 'Schedule Partner', 'address' => 'Santa Cruz', 'contact_person' => 'Supervisor',
                'contact_email' => 'schedule@example.test', 'contact_number' => '09170000000',
                'default_am_start' => '08:00', 'default_am_end' => '12:00',
                'default_pm_start' => '13:00', 'default_pm_end' => '17:00',
                'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'is_active' => true,
            ]);
            DB::table('hte_programs')->insert([
                'hte_id' => $hte->id, 'program_id' => $program->id, 'linked_by' => $coordinator->id,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $studentUser = User::factory()->create(['role' => 'student']);
            $student = Student::create([
                'user_id' => $studentUser->id, 'student_id' => '2027-END-001', 'first_name' => 'Test',
                'last_name' => 'Intern', 'gender' => 'female', 'birth_date' => '2002-01-01',
                'address' => 'Santa Cruz', 'contact_number' => '09171111111',
                'college_id' => $program->college_id, 'program_id' => $program->id,
                'academic_section_id' => $section->id, 'year_level' => 4, 'section' => 'A',
                'parent_name' => 'Parent', 'parent_address' => 'Santa Cruz',
                'parent_contact_number' => '09172222222', 'registration_status' => 'approved',
                'internship_status' => 'pending',
            ]);
            Sanctum::actingAs($head);
            $this->postJson('/api/program-head/ojt-settings/publish', [
                'academic_period_id' => $period->id, 'required_hours' => 16,
                'ojt_start_date' => '2027-01-18',
            ])->assertOk();

            Sanctum::actingAs($coordinator);
            $this->putJson("/api/coordinator/htes/deployments/{$student->id}", [
                'hte_id' => $hte->id, 'required_ojt_hours' => 999,
                'ojt_start_date' => '2026-01-01', 'ojt_end_date' => '2027-01-18',
                'official_am_start' => '08:00', 'official_am_end' => '12:00',
                'official_pm_start' => '13:00', 'official_pm_end' => '17:00',
                'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'internship_status' => 'active', 'allow_past_attendance' => false,
            ])->assertOk();
            $url = "/api/coordinator/htes/deployments/{$student->id}/completion";
            $this->getJson($url)->assertOk()
                ->assertJsonPath('completion.estimated_end_date', '2027-01-19')
                ->assertJsonPath('completion.actual_completion_date', null);
            DB::table('student_schedule_overrides')
                ->where('student_internship_id', $student->currentInternship->id)
                ->where('day_of_week', 'mon')
                ->update(['am_end' => '23:30']);
            $this->getJson($url)->assertOk()
                ->assertJsonPath('completion.estimated_end_date', null)
                ->assertJsonPath('completion.schedule_error', true);
            Sanctum::actingAs($studentUser);
            $this->getJson('/api/student/dashboard')->assertOk()
                ->assertJsonPath('ojt_progress.projected_completion_date', null)
                ->assertJsonPath('ojt_progress.schedule_error', true);
            DB::table('student_schedule_overrides')
                ->where('student_internship_id', $student->currentInternship->id)
                ->where('day_of_week', 'mon')
                ->update(['am_end' => '12:00']);
            Sanctum::actingAs($coordinator);
            Carbon::setTestNow('2027-01-18 18:00:00');
            $this->getJson($url)->assertJsonPath('completion.estimated_end_date', '2027-01-20');

            // Older deployments may still contain a stored end date; it must not cap automatic tracking.
            $student->update(['ojt_end_date' => '2027-01-18']);
            Sanctum::actingAs($studentUser);
            $this->getJson('/api/student/attendance/workspace?month=2027-01&date=2027-01-18')->assertOk()
                ->assertJsonPath('settings.ojt_end_date', null);
            Sanctum::actingAs($coordinator);

            Attendance::create([
                'student_id' => $student->id, 'date' => '2027-01-18',
                'status' => 'absent', 'work_mode' => 'wfo', 'session_type' => 'full_day',
            ]);
            $this->getJson($url)->assertOk()
                ->assertJsonPath('completion.estimated_end_date', '2027-01-20');

            Carbon::setTestNow('2027-01-19 18:00:00');
            $tuesday = Attendance::create([
                'student_id' => $student->id, 'date' => '2027-01-19',
                'status' => 'present', 'work_mode' => 'wfo', 'session_type' => 'full_day',
                'am_time_in' => '2027-01-19 08:00:00', 'am_time_out' => '2027-01-19 12:00:00',
                'pm_time_in' => '2027-01-19 13:00:00',
            ]);
            $this->getJson($url)->assertJsonPath('completion.completed_hours', 0);
            Sanctum::actingAs($studentUser);
            $this->getJson('/api/student/dashboard')->assertOk()
                ->assertJsonPath('ojt_progress.rendered_hours', 0);
            Sanctum::actingAs($coordinator);
            $tuesday->update(['pm_time_out' => '2027-01-19 17:00:00']);
            $this->getJson($url)->assertJsonPath('completion.completed_hours', 8);
            Sanctum::actingAs($studentUser);
            $this->getJson('/api/student/dashboard')->assertOk()
                ->assertJsonPath('ojt_progress.rendered_hours', 8)
                ->assertJsonPath('ojt_progress.percent_complete', 50);
            Sanctum::actingAs($coordinator);
            $this->assertDatabaseMissing('attendance_reviews', ['attendance_id' => $tuesday->id]);

            Carbon::setTestNow('2027-01-20 18:00:00');
            Attendance::create([
                'student_id' => $student->id, 'date' => '2027-01-20',
                'status' => 'present', 'work_mode' => 'wfo', 'session_type' => 'full_day',
                'am_time_in' => '2027-01-20 08:00:00', 'am_time_out' => '2027-01-20 12:00:00',
                'pm_time_in' => '2027-01-20 13:00:00', 'pm_time_out' => '2027-01-20 17:00:00',
            ]);
            $this->getJson($url)->assertOk()
                ->assertJsonPath('completion.completed_hours', 16)
                ->assertJsonPath('completion.remaining_hours', 0)
                ->assertJsonPath('completion.actual_completion_date', '2027-01-20');

            Sanctum::actingAs($studentUser);
            $this->getJson('/api/student/dashboard')->assertOk()
                ->assertJsonPath('ojt_progress.automatic_end_date', true)
                ->assertJsonPath('ojt_progress.actual_completion_date', '2027-01-20')
                ->assertJsonPath('ojt_progress.end_date', '2027-01-20');
        } finally {
            Carbon::setTestNow();
        }
    }

    private function context(): array
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Systems', 'code' => 'BSIS', 'is_active' => true]);
        $period = $this->academicPeriod();
        $section = AcademicSection::create([
            'program_id' => $program->id, 'academic_period_id' => $period->id,
            'name' => 'A', 'year_level' => 4, 'academic_year' => '2026-2027', 'is_active' => true,
        ]);
        $head = User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $section->id, 'coordinator_id' => $coordinator->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$head, $coordinator, $program, $period, $section];
    }
}
