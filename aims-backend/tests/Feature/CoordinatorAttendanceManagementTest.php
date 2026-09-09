<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentLiveLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

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

    public function test_automatic_attendance_is_read_only_and_coordinator_can_mark_a_daily_journal_as_reviewed(): void
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
            ->assertNotFound();

        $this->putJson("/api/coordinator/attendance/{$attendance->id}", [
            'status' => 'absent',
        ])->assertStatus(405);

        $this->postJson("/api/coordinator/attendance/{$attendance->id}/journal-review", [
            'decision' => 'approved',
        ])->assertUnprocessable()->assertJsonValidationErrors('decision');

        $this->postJson("/api/coordinator/attendance/{$attendance->id}/journal-review")
            ->assertOk()
            ->assertJsonPath('message', 'Daily journal marked as reviewed.')
            ->assertJsonPath('attendance.journal_status', 'reviewed');

        $this->assertDatabaseHas('attendance', [
            'id' => $attendance->id,
            'is_verified' => false,
            'verified_by' => null,
            'journal_status' => 'reviewed',
            'journal_feedback' => null,
            'journal_reviewed_by' => $coordinator->id,
        ]);
    }

    public function test_coordinator_can_choose_the_attendance_page_size(): void
    {
        [$coordinator, $student] = $this->context();

        foreach (range(1, 11) as $day) {
            Attendance::create([
                'student_id' => $student->id,
                'date' => sprintf('2026-07-%02d', $day),
                'status' => 'present',
                'work_mode' => 'wfo',
                'session_type' => 'full_day',
            ]);
        }

        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/attendance?per_page=10&page=2')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 11)
            ->assertJsonCount(1, 'data');
    }

    public function test_timed_in_location_remains_visible_until_time_out(): void
    {
        [$coordinator, $student] = $this->context();
        Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
            'am_time_in' => now(),
        ]);
        StudentLiveLocation::create([
            'student_id' => $student->id,
            'latitude' => 13.4753,
            'longitude' => 122.0275,
            'accuracy' => 9.5,
            'captured_at' => now(),
        ]);
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/attendance/live-locations')
            ->assertOk()
            ->assertJsonPath('sharing', 1)
            ->assertJsonPath('total_students', 1)
            ->assertJsonPath('locations.0.student_id', $student->id)
            ->assertJsonPath('locations.0.school_id', '2026-3001')
            ->assertJsonPath('locations.0.status', 'timed_in')
            ->assertJsonPath('locations.0.latitude', 13.4753)
            ->assertJsonPath('locations.0.longitude', 122.0275);

        $student->liveLocation->update(['captured_at' => now()->subMinutes(3)]);

        $this->getJson('/api/coordinator/attendance/live-locations')
            ->assertOk()
            ->assertJsonPath('sharing', 1)
            ->assertJsonCount(1, 'locations');

        Attendance::where('student_id', $student->id)->update(['am_time_out' => now()]);

        $this->getJson('/api/coordinator/attendance/live-locations')
            ->assertOk()
            ->assertJsonPath('sharing', 0)
            ->assertJsonCount(0, 'locations');
    }

    public function test_attendance_history_cannot_be_deleted(): void
    {
        [$coordinator, $student] = $this->context();
        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => '2026-07-20',
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
        ]);
        Sanctum::actingAs($coordinator);

        $this->deleteJson("/api/coordinator/attendance/{$attendance->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('attendance', ['id' => $attendance->id]);
    }

    public function test_report_management_docx_is_reused_for_a_populated_daily_journal_preview(): void
    {
        [$coordinator, $student] = $this->context();
        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => '2026-07-20',
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
            'am_time_in' => '2026-07-20 08:00:00',
            'am_time_out' => '2026-07-20 12:00:00',
            'pm_time_in' => '2026-07-20 13:00:00',
            'pm_time_out' => '2026-07-20 17:00:00',
            'am_activity' => 'Prepared the official morning report.',
            'pm_activity' => 'Updated the afternoon internship records.',
        ]);
        Sanctum::actingAs($coordinator);

        $this->post('/api/report-templates', [
            'name' => 'Official Shared Template',
            'activate' => true,
            'file' => $this->dailyJournalDocx(),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('template.file_name', 'Official Daily Journal.docx');

        $this->assertDatabaseHas('report_templates', [
            'program_id' => $student->program_id,
            'report_type' => 'all_reports',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/coordinator/attendance/{$attendance->id}/journal-preview")
            ->assertOk()
            ->assertJsonPath('template.file_name', 'Official Daily Journal.docx');
        $html = $response->json('html');
        $this->assertStringContainsString('OFFICIAL DAILY JOURNAL FORM', $html);
        $this->assertStringContainsString('8:00 AM', $html);
        $this->assertStringContainsString('12:00 PM', $html);
        $this->assertStringContainsString('Prepared the official morning report.', $html);
        $this->assertStringContainsString('Updated the afternoon internship records.', $html);
    }

    public function test_shared_official_template_upload_rejects_a_corrupted_docx(): void
    {
        [$coordinator] = $this->context();
        Sanctum::actingAs($coordinator);

        $this->post('/api/report-templates', [
            'name' => 'Broken Shared Template',
            'activate' => true,
            'file' => UploadedFile::fake()->createWithContent('broken.docx', 'not a docx zip'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
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
            'contact_number' => '09170000000',
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
            'contact_number' => '09171111111',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09172222222',
            'hte_id' => $hte->id,
            'registration_status' => 'approved',
            'internship_status' => 'active',
        ]);

        return [$coordinator, $student];
    }

    private function dailyJournalDocx(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'aims-journal-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>OFFICIAL DAILY JOURNAL FORM</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>AM Time In</w:t></w:r></w:p></w:tc><w:tc><w:p/></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>AM Time Out</w:t></w:r></w:p></w:tc><w:tc><w:p/></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>AM Accomplishments</w:t></w:r></w:p></w:tc><w:tc><w:p/></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>PM Time In</w:t></w:r></w:p></w:tc><w:tc><w:p/></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>PM Time Out</w:t></w:r></w:p></w:tc><w:tc><w:p/></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>PM Accomplishments</w:t></w:r></w:p></w:tc><w:tc><w:p/></w:tc></w:tr></w:tbl><w:sectPr/></w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'Official Daily Journal.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }
}
