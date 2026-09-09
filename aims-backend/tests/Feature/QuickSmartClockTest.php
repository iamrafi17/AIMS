<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
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

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.use_server_time' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_full_day_smart_clock_enforces_slot_order_and_activity_reports(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 7:55 AM', 'Asia/Manila'));
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

        Carbon::setTestNow(Carbon::parse('2026-08-26 8:00 AM', 'Asia/Manila'));
        $this->postJson('/api/student/attendance/quick-clock', $payload)
            ->assertOk()
            ->assertJsonPath('next_action.key', 'am_time_out')
            ->assertJsonPath('settings_locked', true);

        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00 PM', 'Asia/Manila'));
        $this->postJson('/api/student/attendance/quick-clock', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('activity_required', true);

        $this->postJson('/api/student/attendance/quick-clock', [
            ...$payload,
            'activity' => 'Prepared the morning deployment report.',
        ])->assertOk()->assertJsonPath('next_action.key', 'pm_time_in');

        Carbon::setTestNow(Carbon::parse('2026-08-26 1:00 PM', 'Asia/Manila'));
        $this->postJson('/api/student/attendance/quick-clock', $payload)
            ->assertOk()
            ->assertJsonPath('next_action.key', 'pm_time_out');

        Carbon::setTestNow(Carbon::parse('2026-08-26 5:00 PM', 'Asia/Manila'));
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

    public function test_live_monitoring_starts_after_time_in_and_stops_at_time_out(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-29 7:55 AM', 'Asia/Manila'));
        $user = $this->studentUser();
        $hte = HTE::create([
            'college_id' => $user->student->college_id,
            'program_id' => $user->student->program_id,
            'name' => 'Geofenced Partner',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => 'geofence@example.test',
            'contact_number' => '09170000002',
            'latitude' => 13.4753,
            'longitude' => 122.0275,
            'geofence_radius' => 100,
            'geofence_enabled' => true,
            'is_active' => true,
        ]);
        $user->student->update(['hte_id' => $hte->id]);
        Sanctum::actingAs($user);
        Http::fake();

        $this->postJson('/api/student/attendance/live-location', [
            'latitude' => 13.4753,
            'longitude' => 122.0275,
            'accuracy' => 12.5,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Live monitoring starts only after a successful Time In.');

        $this->postJson('/api/student/attendance/quick-clock', [
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
            'latitude' => 13.4753,
            'longitude' => 122.0275,
            'accuracy' => 8.5,
        ])->assertOk()->assertJsonPath('next_action.key', 'am_time_out');

        $this->assertDatabaseHas('student_live_locations', [
            'student_id' => $user->student->id,
            'latitude' => 13.4753,
            'longitude' => 122.0275,
            'accuracy' => 8.5,
        ]);

        $this->postJson('/api/student/attendance/live-location', [
            'latitude' => 13.4853,
            'longitude' => 122.0275,
            'accuracy' => 12.5,
        ])->assertOk()
            ->assertJsonPath('outside_geofence', true)
            ->assertJsonPath('geofence_radius', 100);

        $this->assertDatabaseHas('student_live_locations', [
            'student_id' => $user->student->id,
            'latitude' => 13.4853,
            'longitude' => 122.0275,
        ]);

        $this->postJson('/api/student/attendance/quick-clock', [
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
            'latitude' => 13.4753,
            'longitude' => 122.0275,
            'activity' => 'Completed the morning work session.',
        ])->assertOk()->assertJsonPath('next_action.key', 'pm_time_in');

        $this->assertDatabaseMissing('student_live_locations', [
            'student_id' => $user->student->id,
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

    public function test_smart_clock_can_use_device_time_in_local_test_mode(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 10:00:00', 'Asia/Manila'));
        config(['attendance.use_server_time' => false]);

        try {
            $user = $this->studentUser();
            Sanctum::actingAs($user);
            Http::fake();

            $payload = [
                'status' => 'present',
                'work_mode' => 'wfh',
                'session_type' => 'full_day',
                'latitude' => 13.4753,
                'longitude' => 122.0275,
            ];

            $this->postJson('/api/student/attendance/quick-clock', [
                ...$payload,
                'client_time' => '2026-03-17T23:00:00.000Z',
            ])->assertOk()
                ->assertJsonPath('uses_server_time', false)
                ->assertJsonPath('next_action.key', 'am_time_out');

            $this->postJson('/api/student/attendance/quick-clock', [
                ...$payload,
                'activity' => 'Tested the device-time clock.',
                'client_time' => '2026-03-18T04:00:00.000Z',
            ])->assertOk()
                ->assertJsonPath('next_action.key', 'pm_time_in');

            $this->postJson('/api/student/attendance/quick-clock', [
                ...$payload,
                'client_time' => '2026-03-18T05:00:00.000Z',
            ])->assertOk()
                ->assertJsonPath('next_action.key', 'pm_time_out');

            $this->postJson('/api/student/attendance/quick-clock', [
                ...$payload,
                'activity' => 'Completed overtime testing.',
                'client_time' => '2026-03-18T09:30:00.000Z',
            ])->assertOk()
                ->assertJsonPath('completed', true)
                ->assertJsonPath('attendance.overtime_hours', 1.5);

            $attendance = Attendance::where('student_id', $user->student->id)->firstOrFail();
            $this->assertSame('07:00', $attendance->am_time_in->format('H:i'));
            $this->assertSame('12:00', $attendance->am_time_out->format('H:i'));
            $this->assertSame('13:00', $attendance->pm_time_in->format('H:i'));
            $this->assertSame('17:30', $attendance->pm_time_out->format('H:i'));
            $this->assertSame('07:00', $attendance->time_in->format('H:i'));
            $this->assertSame('17:30', $attendance->time_out->format('H:i'));
            $this->assertSame('07:00', $attendance->ot_start->format('H:i'));
            $this->assertSame('17:30', $attendance->ot_end->format('H:i'));
            $this->assertSame('1.50', $attendance->overtime_hours);
        } finally {
            Carbon::setTestNow();
        }
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
                ->assertJsonPath('selected_attendance.regular_hours', 8)
                ->assertJsonPath('selected_attendance.total_hours', 12)
                ->assertJsonPath('calendar.0.hours', 12)
                ->assertJsonPath('calendar.0.regular_hours', 8)
                ->assertJsonPath('week_summary.rendered_hours', 12)
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

    public function test_workspace_counts_only_remaining_configured_workdays(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00', 'Asia/Manila'));

        try {
            $user = $this->studentUser();
            $hte = HTE::create([
                'name' => 'Example HTE',
                'address' => 'Santa Cruz, Marinduque',
                'contact_person' => 'HTE Supervisor',
                'contact_email' => 'hte@example.test',
                'contact_number' => '09170000001',
                'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            ]);

            $user->student->update([
                'hte_id' => $hte->id,
                'ojt_start_date' => '2026-08-15',
                'ojt_end_date' => '2026-08-28',
                'work_days' => null,
            ]);
            Sanctum::actingAs($user);

            $this->getJson('/api/student/attendance/workspace?month=2026-08&date=2026-08-15')
                ->assertOk()
                ->assertJsonPath('week_summary.days_left', 0)
                ->assertJsonPath('settings.work_days', ['mon', 'tue', 'wed', 'thu', 'fri']);

            Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00', 'Asia/Manila'));

            $this->getJson('/api/student/attendance/workspace?month=2026-08&date=2026-08-17')
                ->assertOk()
                ->assertJsonPath('week_summary.days_left', 4);
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
            'contact_number' => '09170000000',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Guardian',
            'parent_relationship' => 'Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09180000000',
            'internship_semester' => 'First Semester',
            'internship_year' => '2026-2027',
            'registration_status' => 'approved',
            'internship_status' => 'active',
        ]);

        return $user->load('student');
    }
}
