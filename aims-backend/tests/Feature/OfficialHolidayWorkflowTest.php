<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\College;
use App\Models\Holiday;
use App\Models\HTE;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('holiday.holiday_type', 'local_holiday')
            ->assertJsonPath('holiday.htes.0.id', $gasan->id);

        $holidayId = $response->json('holiday.id');
        $this->assertDatabaseHas('holiday_hte', ['holiday_id' => $holidayId, 'hte_id' => $gasan->id]);

        $this->getJson('/api/coordinator/htes')
            ->assertOk()
            ->assertJsonPath('holiday_forecast.next.name', 'Gasan Founding Anniversary')
            ->assertJsonPath('holiday_forecast.next.days_until', 2)
            ->assertJsonPath('holiday_forecast.next.affected_students', 1)
            ->assertJsonPath('holiday_forecast.next.affected_htes.0.name', 'Gasan Partner');
    }

    public function test_official_holiday_appears_on_student_calendar_and_blocks_every_student_clock_path(): void
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
            ->assertJsonPath('clock_disabled', true)
            ->assertJsonPath('completed', true)
            ->assertJsonPath('today_holiday.name', 'Official National Holiday');

        $this->postJson('/api/student/attendance/quick-clock', [
            'status' => 'present',
            'work_mode' => 'wfh',
            'session_type' => 'full_day',
            'latitude' => 13.47,
            'longitude' => 122.02,
        ])->assertUnprocessable()
            ->assertJsonPath('today_holiday.name', 'Official National Holiday');

        $this->postJson('/api/student/attendance/quick-clock', ['status' => 'holiday'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

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
            ->assertJsonValidationErrors('date');

        $this->getJson('/api/student/dashboard')
            ->assertOk()
            ->assertJsonPath('ojt_progress.working_days_left', 22)
            ->assertJsonPath('holiday_forecast.next.is_today', true);

        $this->assertFalse(
            Attendance::forStudent($user->student->id)->whereDate('date', '2026-09-22')->exists()
        );
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

    private function academicContext(): array
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'BSIT',
            'is_active' => true,
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
        Student::create([
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

        return $user->load('student');
    }
}
