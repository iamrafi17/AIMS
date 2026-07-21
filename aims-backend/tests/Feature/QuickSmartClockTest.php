<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\College;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickSmartClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_day_smart_clock_enforces_slot_order_and_activity_reports(): void
    {
        $user = $this->studentUser();
        Sanctum::actingAs($user);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'display_name' => 'Santa Cruz, Marinduque, Philippines',
            ]),
        ]);

        $payload = [
            'status' => 'present',
            'work_mode' => 'wfh',
            'session_type' => 'full_day',
            'latitude' => 13.4753,
            'longitude' => 122.0275,
        ];

        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()
            ->assertJsonPath('timezone', 'Asia/Manila')
            ->assertJsonPath('attendance', null)
            ->assertJsonPath('settings_locked', false);

        $this->postJson('/api/student/attendance/quick-clock', $payload)
            ->assertOk()
            ->assertJsonPath('next_action.key', 'am_time_out')
            ->assertJsonPath('settings_locked', true);

        $this->postJson('/api/student/attendance/quick-clock', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('activity_required', true);

        $this->postJson('/api/student/attendance/quick-clock', [
            ...$payload,
            'activity' => 'Prepared the morning deployment report.',
        ])->assertOk()->assertJsonPath('next_action.key', 'pm_time_in');

        $this->postJson('/api/student/attendance/quick-clock', $payload)
            ->assertOk()
            ->assertJsonPath('next_action.key', 'pm_time_out');

        $this->postJson('/api/student/attendance/quick-clock', [
            ...$payload,
            'activity' => 'Updated records and joined the afternoon team meeting.',
        ])->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('just_completed', true)
            ->assertJsonPath('next_action', null);

        $this->assertDatabaseHas('attendance', [
            'student_id' => $user->student->id,
            'status' => 'present',
            'work_mode' => 'wfh',
            'session_type' => 'full_day',
            'am_activity' => 'Prepared the morning deployment report.',
            'pm_activity' => 'Updated records and joined the afternoon team meeting.',
        ]);
    }

    public function test_absent_status_completes_the_day_and_cannot_be_changed(): void
    {
        $user = $this->studentUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/student/attendance/quick-clock', [
            'status' => 'absent',
        ])->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('settings_locked', true)
            ->assertJsonPath('attendance.status', 'absent');

        $this->postJson('/api/student/attendance/quick-clock', [
            'status' => 'holiday',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('attendance', [
            'student_id' => $user->student->id,
            'status' => 'absent',
        ]);
    }

    public function test_student_dashboard_returns_database_driven_ojt_progress(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Asia/Manila'));

        try {
            $user = $this->studentUser();
            $user->student->update([
                'consent_status' => 'done',
                'schedule_status' => 'approved',
                'ojt_start_date' => '2026-03-01',
                'ojt_end_date' => '2026-03-31',
                'required_ojt_hours' => 486,
            ]);

            Attendance::create([
                'student_id' => $user->student->id,
                'date' => '2026-03-16',
                'work_mode' => 'wfh',
                'session_type' => 'full_day',
                'status' => 'present',
                'am_time_in' => '2026-03-16 08:00:00',
                'am_time_out' => '2026-03-16 12:00:00',
                'pm_time_in' => '2026-03-16 13:00:00',
                'pm_time_out' => '2026-03-16 17:00:00',
                'am_activity' => 'Morning tasks',
                'pm_activity' => 'Afternoon tasks',
            ]);

            Attendance::create([
                'student_id' => $user->student->id,
                'date' => '2026-03-17',
                'work_mode' => 'wfh',
                'session_type' => 'am_half',
                'status' => 'late',
                'am_time_in' => '2026-03-17 08:00:00',
                'am_time_out' => '2026-03-17 12:00:00',
                'am_activity' => 'Morning tasks',
            ]);

            Sanctum::actingAs($user);

            $this->getJson('/api/student/dashboard')
                ->assertOk()
                ->assertJsonPath('dashboard_status.consent', 'done')
                ->assertJsonPath('dashboard_status.schedule', 'approved')
                ->assertJsonPath('dashboard_status.attendance_days', 2)
                ->assertJsonPath('ojt_progress.rendered_hours', 12)
                ->assertJsonPath('ojt_progress.hours_left', 474)
                ->assertJsonPath('ojt_progress.percent_complete', 2.5)
                ->assertJsonPath('ojt_progress.entries', 3)
                ->assertJsonPath('ojt_progress.days_left', 13)
                ->assertJsonPath('ojt_progress.weekly_hours.0.hours', 8)
                ->assertJsonPath('ojt_progress.weekly_hours.1.hours', 4)
                ->assertJsonPath('ojt_progress.period_state', 'active');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_past_manual_entry_calculates_only_effective_overtime(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Asia/Manila'));

        try {
            $user = $this->studentUser();
            $user->student->update([
                'allow_past_attendance' => true,
                'ojt_start_date' => '2026-03-01',
                'ojt_end_date' => '2026-03-31',
            ]);
            Sanctum::actingAs($user);

            $this->postJson('/api/student/attendance/entry', [
                'date' => '2026-03-17',
                'status' => 'present',
                'work_mode' => 'wfh',
                'session_type' => 'full_day',
                'am_time_in' => '08:00',
                'am_time_out' => '12:00',
                'pm_time_in' => '13:00',
                'pm_time_out' => '17:00',
                'ot_start' => '06:00',
                'ot_end' => '19:00',
                'am_activity' => 'Morning work',
                'pm_activity' => 'Afternoon work',
            ])->assertOk()
                ->assertJsonPath('attendance.regular_hours', 8)
                ->assertJsonPath('attendance.overtime_hours', 4)
                ->assertJsonCount(1, 'warnings');

            $this->getJson('/api/student/attendance/workspace?month=2026-03&date=2026-03-17')
                ->assertOk()
                ->assertJsonPath('selected_attendance.overtime_hours', 4)
                ->assertJsonPath('week_summary.rendered_hours', 8)
                ->assertJsonPath('week_summary.overtime_hours', 4)
                ->assertJsonPath('settings.allow_past_attendance', true);

            $this->postJson('/api/student/attendance/entry', [
                'date' => '2026-03-17',
                'status' => 'present',
                'work_mode' => 'field',
                'session_type' => 'am_half',
                'am_time_in' => '08:00',
                'am_time_out' => '12:00',
                'am_activity' => 'Updated morning work',
            ])->assertOk();

            $this->assertDatabaseCount('attendance', 1);
            $this->assertDatabaseHas('attendance', [
                'work_mode' => 'field',
                'session_type' => 'am_half',
                'am_activity' => 'Updated morning work',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function studentUser(): User
    {
        $college = College::create([
            'name' => 'College of Information and Computing Sciences',
            'code' => 'CICS',
            'is_active' => true,
        ]);

        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Bachelor of Science in Information Technology',
            'code' => 'BSIT',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => 'student']);

        Student::create([
            'user_id' => $user->id,
            'student_id' => 'TEST-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'first_name' => 'Test',
            'last_name' => 'Student',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'phone' => '09170000000',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Guardian',
            'parent_relationship' => 'Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_phone' => '09180000000',
            'internship_semester' => 'First Semester',
            'internship_year' => '2026-2027',
            'registration_status' => 'approved',
            'internship_status' => 'active',
        ]);

        return $user->load('student');
    }
}
