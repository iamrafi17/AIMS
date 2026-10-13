<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\College;
use App\Models\Holiday;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Support\AttendanceHours;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficialHolidayWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_coordinator_sets_a_verified_local_holiday_and_forecast_counts_only_affected_hte_students(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 08:00:00', 'Asia/Manila'));
        [$college, $program] = $this->academicContext();
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ]);
        $gasan = $this->hte($college, $program, 'Gasan Partner', 'Gasan, Marinduque');
        $boac = $this->hte($college, $program, 'Boac Partner', 'Boac, Marinduque');
        $this->student($college, $program, $gasan);
        $this->student($college, $program, $boac);
        Sanctum::actingAs($coordinator);

        $response = $this->postJson('/api/coordinator/htes/holidays', [
            'name' => 'Gasan Founding Anniversary',
            'date' => '2026-09-21',
            'holiday_type' => 'local_holiday',
            'affected_area' => 'Municipality of Gasan, Marinduque',
            'hte_ids' => [$gasan->id],
            'official_reference' => 'Proclamation No. 1400',
            'source_url' => 'https://example.gov.ph/proclamation-1400',
            'description' => 'Official local non-working day.',
            'is_recurring' => false,
        ])->assertCreated()
            ->assertJsonPath('holiday.date', '2026-09-21')
            ->assertJsonPath('holiday.holiday_type', 'local_holiday')
            ->assertJsonPath('holiday.htes.0.id', $gasan->id);

        $holidayId = $response->json('holiday.id');
        $this->assertDatabaseHas('holiday_hte', ['holiday_id' => $holidayId, 'hte_id' => $gasan->id]);

        $this->getJson('/api/coordinator/htes')
            ->assertOk()
            ->assertJsonPath('holidays.0.date', '2026-09-21')
            ->assertJsonPath('holiday_forecast.next.name', 'Gasan Founding Anniversary')
            ->assertJsonPath('holiday_forecast.next.days_until', 2)
            ->assertJsonPath('holiday_forecast.next.affected_students', 1)
            ->assertJsonPath('holiday_forecast.next.affected_htes.0.name', 'Gasan Partner');
    }

    public function test_official_holiday_appears_on_calendar_and_can_be_recorded_manually_without_hours(): void
    {
        [$college, $program] = $this->academicContext();
        $hte = $this->hte($college, $program, 'National Partner', 'Boac, Marinduque');
        $user = $this->student($college, $program, $hte, 176);
        Holiday::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => 'Official National Holiday',
            'date' => '2026-09-22',
            'holiday_type' => 'national_holiday',
            'official_reference' => 'Proclamation No. 1500',
            'description' => 'Nationwide regular holiday.',
            'is_recurring' => false,
        ]);
        Sanctum::actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-09-21 08:00:00', 'Asia/Manila'));
        $this->getJson('/api/student/dashboard')
            ->assertOk()
            ->assertJsonPath('ojt_progress.working_days_left', 22)
            ->assertJsonPath('holiday_forecast.next.name', 'Official National Holiday')
            ->assertJsonPath('holiday_forecast.next.days_until', 1);
        $this->getJson('/api/student/attendance/workspace?month=2026-09&date=2026-09-21')
            ->assertOk()
            ->assertJsonPath('week_summary.days_left', 3);

        Carbon::setTestNow(Carbon::parse('2026-09-22 08:00:00', 'Asia/Manila'));
        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()
            ->assertJsonPath('clock_disabled', false)
            ->assertJsonPath('completed', false)
            ->assertJsonPath('settings_locked', false)
            ->assertJsonPath('today_holiday.name', 'Official National Holiday');

        $this->postJson('/api/student/attendance/quick-clock', ['status' => 'holiday'])
            ->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('settings_locked', true)
            ->assertJsonPath('attendance.status', 'holiday');

        $holidayAttendance = Attendance::forStudent($user->student->id)
            ->whereDate('date', '2026-09-22')->firstOrFail();
        $this->assertSame('holiday', $holidayAttendance->status);
        $this->assertSame(Holiday::where('name', 'Official National Holiday')->value('id'), $holidayAttendance->holiday_id);
        $this->postJson('/api/student/attendance/quick-clock', ['status' => 'holiday'])
            ->assertUnprocessable();

        $this->getJson('/api/student/attendance/workspace?month=2026-09&date=2026-09-22')
            ->assertOk()
            ->assertJsonPath('holidays.0.name', 'Official National Holiday')
            ->assertJsonPath('selected_holiday.name', 'Official National Holiday');

        $this->postJson('/api/student/attendance/entry', [
            'date' => '2026-09-22',
            'status' => 'present',
            'work_mode' => 'wfh',
            'session_type' => 'full_day',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->getJson('/api/student/dashboard')
            ->assertOk()
            ->assertJsonPath('ojt_progress.working_days_left', 22)
            ->assertJsonPath('holiday_forecast.next.is_today', true);

        $this->assertSame(0.0, AttendanceHours::total($holidayAttendance));
    }

    public function test_local_holiday_does_not_block_students_assigned_to_an_unselected_hte(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-23 08:00:00', 'Asia/Manila'));
        [$college, $program] = $this->academicContext();
        $affectedHte = $this->hte($college, $program, 'Affected Partner', 'Gasan, Marinduque');
        $otherHte = $this->hte($college, $program, 'Other Partner', 'Boac, Marinduque');
        $holiday = Holiday::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => 'Gasan Local Holiday',
            'date' => '2026-09-23',
            'holiday_type' => 'local_holiday',
            'official_reference' => 'Proclamation No. 1501',
            'affected_area' => 'Municipality of Gasan, Marinduque',
            'is_recurring' => false,
        ]);
        $holiday->htes()->attach($affectedHte->id);

        $unaffectedUser = $this->student($college, $program, $otherHte);
        Sanctum::actingAs($unaffectedUser);

        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()
            ->assertJsonPath('clock_disabled', false)
            ->assertJsonPath('today_holiday', null);

        $this->getJson('/api/student/attendance/workspace?month=2026-09&date=2026-09-23')
            ->assertOk()
            ->assertJsonCount(0, 'holidays')
            ->assertJsonPath('selected_holiday', null);
    }

    public function test_student_who_actually_works_on_a_saved_holiday_can_clock_in_and_record_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-08 08:00:00', 'Asia/Manila'));
        [$college, $program] = $this->academicContext();
        $hte = $this->hte($college, $program, 'Working Holiday Partner', 'Boac, Marinduque');
        $user = $this->student($college, $program, $hte);
        Holiday::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => 'Special Non-working Day',
            'date' => '2026-12-08',
            'holiday_type' => 'special_non_working',
            'official_reference' => 'Test proclamation',
            'is_recurring' => false,
        ]);
        Sanctum::actingAs($user);
        Http::fake();

        $this->getJson('/api/student/attendance/quick-clock')->assertOk()
            ->assertJsonPath('today_holiday.name', 'Special Non-working Day')
            ->assertJsonPath('clock_disabled', false);
        $this->postJson('/api/student/attendance/quick-clock', [
            'status' => 'present', 'work_mode' => 'wfh', 'session_type' => 'full_day',
            'latitude' => 13.47, 'longitude' => 122.02,
            'client_time' => '2026-12-08T00:00:00Z',
        ])->assertOk()
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('next_action.key', 'am_time_out');
        $this->postJson('/api/student/attendance/live-location', [
            'latitude' => 13.47, 'longitude' => 122.02,
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-12-08 12:00:00', 'Asia/Manila'));
        $this->postJson('/api/student/attendance/quick-clock', [
            'status' => 'present', 'work_mode' => 'wfh', 'session_type' => 'full_day',
            'latitude' => 13.47, 'longitude' => 122.02,
            'client_time' => '2026-12-08T04:00:00Z',
            'activity' => 'Completed morning OJT work.',
        ])->assertOk()->assertJsonPath('next_action.key', 'pm_time_in');

        Carbon::setTestNow(Carbon::parse('2026-12-08 13:00:00', 'Asia/Manila'));
        $this->postJson('/api/student/attendance/quick-clock', [
            'status' => 'present', 'work_mode' => 'wfh', 'session_type' => 'full_day',
            'latitude' => 13.47, 'longitude' => 122.02,
            'client_time' => '2026-12-08T05:00:00Z',
        ])->assertOk()->assertJsonPath('next_action.key', 'pm_time_out');

        Carbon::setTestNow(Carbon::parse('2026-12-08 17:00:00', 'Asia/Manila'));
        $this->postJson('/api/student/attendance/quick-clock', [
            'status' => 'present', 'work_mode' => 'wfh', 'session_type' => 'full_day',
            'latitude' => 13.47, 'longitude' => 122.02,
            'client_time' => '2026-12-08T09:00:00Z',
            'activity' => 'Completed afternoon OJT work.',
        ])->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('today_holiday.name', 'Special Non-working Day');

        $attendance = Attendance::forStudent($user->student->id)
            ->whereDate('date', '2026-12-08')->firstOrFail();
        $this->assertSame('present', $attendance->status);
        $this->assertEqualsWithDelta(8, AttendanceHours::total($attendance), 0.001);
        $this->assertFalse(Attendance::verified()->whereKey($attendance->id)->exists());
        $reviewer = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        AttendanceReview::create([
            'attendance_id' => $attendance->id,
            'review_type' => 'verification',
            'decision' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
        $this->assertTrue(Attendance::verified()->whereKey($attendance->id)->exists());
    }

    public function test_permitted_past_entry_can_record_real_work_on_a_saved_holiday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-09 09:00:00', 'Asia/Manila'));
        [$college, $program] = $this->academicContext();
        $hte = $this->hte($college, $program, 'Past Holiday Partner', 'Boac, Marinduque');
        $user = $this->student($college, $program, $hte);
        $user->student->update(['allow_past_attendance' => true]);
        Holiday::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => 'Special Non-working Day',
            'date' => '2026-12-08',
            'holiday_type' => 'special_non_working',
            'official_reference' => 'Test proclamation',
            'is_recurring' => false,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/student/attendance/entry', [
            'date' => '2026-12-08', 'status' => 'present',
            'work_mode' => 'wfh', 'session_type' => 'am_half',
            'am_time_in' => '08:00', 'am_time_out' => '11:30',
            'am_activity' => 'Completed assigned OJT work.',
        ])->assertOk()->assertJsonPath('attendance.status', 'present');

        $attendance = Attendance::forStudent($user->student->id)
            ->whereDate('date', '2026-12-08')->firstOrFail();
        $this->assertEqualsWithDelta(3.5, AttendanceHours::total($attendance), 0.001);
    }

    public function test_recurring_fixed_date_holiday_is_forecast_for_the_next_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-26 08:00:00', 'Asia/Manila'));
        [$college, $program] = $this->academicContext();
        $hte = $this->hte($college, $program, 'Recurring Partner', 'Boac, Marinduque');
        $user = $this->student($college, $program, $hte);
        $user->student->update(['ojt_end_date' => '2028-03-31']);
        Holiday::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => 'Christmas Day',
            'date' => '2020-12-25',
            'holiday_type' => 'national_holiday',
            'official_reference' => 'Fixed-date national holiday',
            'is_recurring' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/student/dashboard')
            ->assertOk()
            ->assertJsonPath('holiday_forecast.next.date', '2027-12-25')
            ->assertJsonPath('holiday_forecast.next.name', 'Christmas Day');

        $this->getJson('/api/student/attendance/workspace?month=2027-12&date=2027-12-25')
            ->assertOk()
            ->assertJsonPath('holidays.0.date', '2027-12-25')
            ->assertJsonPath('selected_holiday.name', 'Christmas Day');
    }

    public function test_hte_selection_is_required_only_for_local_holidays(): void
    {
        [$college, $program] = $this->academicContext();
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ]);
        Sanctum::actingAs($coordinator);

        $this->postJson('/api/coordinator/htes/holidays', [
            'name' => 'Nationwide Holiday',
            'date' => '2026-12-30',
            'holiday_type' => 'national_holiday',
            'official_reference' => 'Proclamation No. 1600',
            'hte_ids' => [],
            'is_recurring' => false,
        ])->assertCreated();

        $this->postJson('/api/coordinator/htes/holidays', [
            'name' => 'Local Holiday Without HTE',
            'date' => '2026-12-31',
            'holiday_type' => 'local_holiday',
            'affected_area' => 'Municipality of Gasan, Marinduque',
            'official_reference' => 'Proclamation No. 1601',
            'hte_ids' => [],
            'is_recurring' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('hte_ids');
    }

    public function test_imported_national_holidays_reach_students_and_local_holidays_stay_with_affected_hte(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-31 08:00:00', 'Asia/Manila'));
        [$college, $program] = $this->academicContext();
        $coordinator = User::factory()->create([
            'role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id,
        ]);
        $gasan = $this->hte($college, $program, 'Gasan Partner', 'Gasan, Marinduque');
        $boac = $this->hte($college, $program, 'Boac Partner', 'Boac, Marinduque');
        $gasanStudent = $this->student($college, $program, $gasan);
        $boacStudent = $this->student($college, $program, $boac);
        $otherProgram = Program::create([
            'college_id' => $college->id, 'name' => 'Computer Science', 'code' => 'BSCS', 'is_active' => true,
        ]);
        $otherHte = $this->hte($college, $otherProgram, 'Other Program Partner', 'Boac, Marinduque');
        $otherProgramStudent = $this->student($college, $otherProgram, $otherHte);
        Sanctum::actingAs($boacStudent);
        $this->getJson('/api/student/dashboard')->assertOk()
            ->assertJsonPath('holiday_forecast.next', null)
            ->assertJsonCount(0, 'holiday_forecast.upcoming');
        Http::fake(['nagerholidays.com/*' => Http::response([
            ['date' => '2027-01-01', 'name' => "New Year's Day", 'countryCode' => 'PH', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
            ['date' => '2027-01-03', 'name' => 'Regional Day', 'countryCode' => 'PH', 'nationalHoliday' => false, 'holidayTypes' => ['Public']],
        ])]);

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinator/htes/holidays/import-preview?year=2027')
            ->assertOk()->assertJsonCount(1, 'holidays')
            ->assertJsonPath('holidays.0.name', "New Year's Day")
            ->assertJsonPath('holidays.0.already_saved', false);
        $this->postJson('/api/coordinator/htes/holidays/import', [
            'year' => 2027, 'dates' => ['2027-01-01'], 'official_reference' => 'Proclamation No. 123',
        ])->assertOk()->assertJsonPath('imported', 1);
        $this->getJson('/api/coordinator/htes/holidays/import-preview?year=2027')
            ->assertOk()->assertJsonPath('holidays.0.already_saved', true);
        $this->postJson('/api/coordinator/htes/holidays/import', [
            'year' => 2027, 'dates' => ['2027-01-01'], 'official_reference' => 'Proclamation No. 123',
        ])->assertOk()->assertJsonPath('imported', 0)->assertJsonPath('skipped', 1);
        $this->postJson('/api/coordinator/htes/holidays/import', [
            'year' => 2027, 'dates' => ['2027-01-03'], 'official_reference' => 'Proclamation No. 123',
        ])->assertUnprocessable()->assertJsonValidationErrors('dates');
        $this->assertDatabaseCount('holidays', 2);

        $local = Holiday::create([
            'college_id' => $college->id, 'program_id' => $program->id,
            'name' => 'Gasan Local Holiday', 'date' => '2027-01-02',
            'holiday_type' => 'local_holiday', 'affected_area' => 'Gasan, Marinduque',
            'official_reference' => 'Local Proclamation', 'is_recurring' => false,
        ]);
        $local->htes()->attach($gasan->id);
        Holiday::create([
            'college_id' => $college->id, 'program_id' => $program->id,
            'name' => 'Special Non-working Day', 'date' => '2027-01-03',
            'holiday_type' => 'special_non_working',
            'official_reference' => 'Proclamation No. 124', 'is_recurring' => false,
        ]);

        Sanctum::actingAs($gasanStudent);
        $this->getJson('/api/student/dashboard')->assertOk()
            ->assertJsonPath('holiday_forecast.upcoming.0.name', "New Year's Day")
            ->assertJsonPath('holiday_forecast.upcoming.1.name', 'Gasan Local Holiday')
            ->assertJsonPath('holiday_forecast.upcoming.2.name', 'Special Non-working Day');
        Sanctum::actingAs($boacStudent);
        $this->getJson('/api/student/dashboard')->assertOk()
            ->assertJsonPath('holiday_forecast.upcoming.0.name', "New Year's Day")
            ->assertJsonPath('holiday_forecast.upcoming.1.name', 'Special Non-working Day')
            ->assertJsonCount(2, 'holiday_forecast.upcoming');
        Sanctum::actingAs($otherProgramStudent);
        $this->getJson('/api/student/dashboard')->assertOk()
            ->assertJsonPath('holiday_forecast.upcoming.0.name', "New Year's Day")
            ->assertJsonCount(1, 'holiday_forecast.upcoming');
    }

    public function test_import_preview_skips_recurring_national_holiday_but_not_a_local_holiday_on_same_date(): void
    {
        [$college, $program] = $this->academicContext();
        $coordinator = User::factory()->create([
            'role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id,
        ]);
        $hte = $this->hte($college, $program, 'Marinduque Partner', 'Boac, Marinduque');
        $local = Holiday::create([
            'college_id' => $college->id, 'program_id' => $program->id,
            'name' => 'Local Day', 'date' => '2027-01-01', 'holiday_type' => 'local_holiday',
            'affected_area' => 'Marinduque', 'official_reference' => 'Local proclamation',
        ]);
        $local->htes()->attach($hte->id);
        Http::fake(['nagerholidays.com/*' => Http::response([
            ['date' => '2027-01-01', 'name' => "New Year's Day", 'countryCode' => 'PH', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
        ])]);
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/htes/holidays/import-preview?year=2027')
            ->assertOk()->assertJsonPath('holidays.0.already_saved', false);
        $this->postJson('/api/coordinator/htes/holidays/import', [
            'year' => 2027, 'dates' => ['2027-01-01'], 'official_reference' => 'National proclamation',
        ])->assertOk()->assertJsonPath('imported', 1);
        $this->assertDatabaseCount('holidays', 2);

        Holiday::where('name', "New Year's Day")->update(['date' => '2020-01-01', 'is_recurring' => true]);
        $this->getJson('/api/coordinator/htes/holidays/import-preview?year=2027')
            ->assertOk()->assertJsonPath('holidays.0.already_saved', true);
    }

    public function test_student_monthly_forecast_lists_december_holidays_even_after_ojt_end_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 08:00:00', 'Asia/Manila'));
        [$college, $program] = $this->academicContext();
        $hte = $this->hte($college, $program, 'December Partner', 'Boac, Marinduque');
        $user = $this->student($college, $program, $hte);
        $user->student->update(['ojt_end_date' => '2026-12-13']);
        $coordinator = User::factory()->create([
            'role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id,
        ]);
        Http::fake(['nagerholidays.com/*' => Http::response([
            ['date' => '2026-12-08', 'name' => 'Feast of the Immaculate Conception of Mary', 'countryCode' => 'PH', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
            ['date' => '2026-12-25', 'name' => 'Christmas Day', 'countryCode' => 'PH', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
            ['date' => '2026-12-30', 'name' => 'Rizal Day', 'countryCode' => 'PH', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
        ])]);
        Sanctum::actingAs($coordinator);
        $this->postJson('/api/coordinator/htes/holidays/import', [
            'year' => 2026,
            'dates' => ['2026-12-08', '2026-12-25', '2026-12-30'],
            'holiday_types' => [
                '2026-12-08' => 'special_non_working',
                '2026-12-25' => 'national_holiday',
                '2026-12-30' => 'national_holiday',
            ],
            'official_reference' => 'Proclamation No. 1006',
        ])->assertOk()->assertJsonPath('imported', 3);
        Sanctum::actingAs($user);
        Carbon::setTestNow(Carbon::parse('2026-12-27 08:00:00', 'Asia/Manila'));

        $this->getJson('/api/student/dashboard')->assertOk()
            ->assertJsonPath('holiday_forecast.month', '2026-12')
            ->assertJsonPath('holiday_forecast.this_month.0.date', '2026-12-08')
            ->assertJsonPath('holiday_forecast.this_month.0.holiday_type', 'special_non_working')
            ->assertJsonPath('holiday_forecast.this_month.1.name', 'Christmas Day')
            ->assertJsonPath('holiday_forecast.this_month.2.name', 'Rizal Day')
            ->assertJsonCount(3, 'holiday_forecast.this_month');
    }

    public function test_student_monthly_forecast_shows_past_september_holiday_without_december(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-27 08:00:00', 'Asia/Manila'));
        [$college, $program] = $this->academicContext();
        $hte = $this->hte($college, $program, 'September Partner', 'Boac, Marinduque');
        $user = $this->student($college, $program, $hte);
        foreach ([
            ['2026-09-20', 'Try', 'special_non_working'],
            ['2026-12-25', 'Christmas Day', 'national_holiday'],
        ] as [$date, $name, $type]) {
            Holiday::create([
                'college_id' => $college->id, 'program_id' => $program->id,
                'date' => $date, 'name' => $name, 'holiday_type' => $type,
                'official_reference' => 'Test reference', 'is_recurring' => false,
            ]);
        }
        Sanctum::actingAs($user);

        $this->getJson('/api/student/dashboard')->assertOk()
            ->assertJsonPath('holiday_forecast.month', '2026-09')
            ->assertJsonPath('holiday_forecast.this_month.0.date', '2026-09-20')
            ->assertJsonPath('holiday_forecast.this_month.0.name', 'Try')
            ->assertJsonCount(1, 'holiday_forecast.this_month');
    }

    private function academicContext(): array
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'BSIT',
            'is_active' => true,
            'smart_clock_enabled' => true,
        ]);

        return [$college, $program];
    }

    private function hte(College $college, Program $program, string $name, string $address): HTE
    {
        $hte = HTE::create([
            'name' => $name,
            'address' => $address,
            'contact_person' => 'HTE Supervisor',
            'contact_email' => str($name)->slug().'-hte@example.test',
            'contact_number' => '09170000001',
            'geofence_radius' => 100,
            'geofence_enabled' => false,
            'default_am_start' => '08:00',
            'default_am_end' => '12:00',
            'default_pm_start' => '13:00',
            'default_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
        ]);
        $hte->programs()->attach($program->id, ['is_active' => true]);

        return $hte;
    }

    private function student(College $college, Program $program, HTE $hte, int $requiredHours = 486): User
    {
        $user = User::factory()->create(['role' => 'student']);
        $student = Student::create([
            'user_id' => $user->id,
            'student_id' => 'HOL-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'first_name' => 'Holiday',
            'last_name' => 'Student '.$user->id,
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Boac, Marinduque',
            'contact_number' => '09171111111',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_period_id' => $this->academicPeriod()->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Boac, Marinduque',
            'parent_contact_number' => '09172222222',
            'hte_id' => $hte->id,
            'registration_status' => 'approved',
            'consent_status' => 'done',
            'internship_status' => 'active',
            'ojt_start_date' => '2026-09-01',
            'ojt_end_date' => '2027-03-31',
            'required_ojt_hours' => $requiredHours,
            'official_am_start' => '08:00',
            'official_am_end' => '12:00',
            'official_pm_start' => '13:00',
            'official_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ]);

        InternshipRequirement::create([
            'student_id' => $student->id,
            'requirement_name' => "Notarized Parent's/Guardian's Consent",
            'file_path' => 'requirements/test-consent.pdf',
            'status' => 'approved',
        ]);

        return $user->load('student');
    }
}
