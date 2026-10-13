<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\AcademicSection;
use App\Models\College;
use App\Models\HTE;
use App\Models\MOA;
use App\Models\Program;
use App\Models\ReportTemplate;
use App\Models\Student;
use App\Models\StudentInternship;
use App\Models\StudentLiveLocation;
use App\Models\User;
use App\Services\ReportTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $this->getJson('/api/coordinator/attendance?search=2026-3001')
            ->assertOk()
            ->assertJsonPath('total', 2);
        $this->getJson('/api/coordinator/attendance?search=Intern')
            ->assertOk()
            ->assertJsonPath('total', 2);
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

        $this->assertDatabaseMissing('attendance_reviews', [
            'attendance_id' => $attendance->id,
            'review_type' => 'verification',
            'decision' => 'approved',
        ]);
        $this->assertDatabaseHas('attendance_journals', [
            'attendance_id' => $attendance->id,
            'period' => 'am',
            'review_status' => 'reviewed',
            'feedback' => null,
            'reviewed_by' => $coordinator->id,
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

        Attendance::forStudent($student->id)->firstOrFail()->update(['am_time_out' => now()]);

        $this->getJson('/api/coordinator/attendance/live-locations')
            ->assertOk()
            ->assertJsonPath('sharing', 0)
            ->assertJsonCount(0, 'locations');
    }

    public function test_dean_live_map_is_limited_to_active_fresh_locations_in_assigned_college(): void
    {
        [, $student] = $this->context();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $student->college_id]);
        $otherCollege = College::create(['name' => 'Education', 'code' => 'CED', 'is_active' => true]);
        $otherDean = User::factory()->create(['role' => 'dean', 'college_id' => $otherCollege->id]);
        Attendance::create([
            'student_id' => $student->id, 'date' => now()->toDateString(),
            'status' => 'present', 'work_mode' => 'wfo', 'session_type' => 'full_day', 'am_time_in' => now(),
        ]);
        $location = StudentLiveLocation::create([
            'student_id' => $student->id, 'latitude' => 13.4753, 'longitude' => 122.0275,
            'accuracy' => 9.5, 'captured_at' => now(),
        ]);

        Sanctum::actingAs($dean->fresh());
        $this->getJson('/api/dean/live-locations')->assertOk()->assertJsonPath('sharing', 1)
            ->assertJsonPath('locations.0.student_id', $student->id);
        Sanctum::actingAs($otherDean->fresh());
        $this->getJson('/api/dean/live-locations')->assertOk()->assertJsonPath('sharing', 0);

        $location->update(['captured_at' => now()->subMinutes(2)]);
        Sanctum::actingAs($dean->fresh());
        $this->getJson('/api/dean/live-locations')->assertOk()->assertJsonPath('sharing', 0);
        $location->update(['captured_at' => now()]);
        Attendance::forStudent($student->id)->firstOrFail()->update(['am_time_out' => now()]);
        $this->getJson('/api/dean/live-locations')->assertOk()->assertJsonPath('sharing', 0);
    }

    public function test_dean_dashboard_counts_current_internships_without_querying_removed_student_columns(): void
    {
        [, $student] = $this->context();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $student->college_id]);
        Sanctum::actingAs($dean->fresh());

        $this->getJson('/api/dean/dashboard')->assertOk()
            ->assertJsonPath('college.code', 'CICS')
            ->assertJsonPath('students', 1)
            ->assertJsonPath('active_students', 1)
            ->assertJsonPath('pending_deployments', 0)
            ->assertJsonPath('pending_moas', 0)
            ->assertJsonPath('pending_travel_reports', 0);
    }

    public function test_dean_dashboard_scopes_period_program_and_verified_attendance(): void
    {
        [, $student] = $this->context();
        $current = $this->academicPeriod();
        $current->update(['status' => 'active']);
        $internship = $student->currentInternship()->firstOrFail();
        $internship->update(['required_hours' => 8]);
        $oldPeriod = $this->academicPeriod('2025-2026', '1st Semester');
        $oldSection = AcademicSection::create([
            'program_id' => $student->program_id, 'name' => 'Old OJT', 'year_level' => 4,
            'academic_period_id' => $oldPeriod->id, 'is_active' => false,
        ]);
        StudentInternship::create([
            'student_id' => $student->id, 'academic_section_id' => $oldSection->id,
            'status' => 'completed', 'is_current' => false, 'required_hours' => 8,
        ]);
        $otherCollege = College::create(['name' => 'Education', 'code' => 'CED', 'is_active' => true]);
        $otherProgram = Program::create(['college_id' => $otherCollege->id, 'name' => 'Education', 'code' => 'BSED', 'is_active' => true]);
        $otherSection = AcademicSection::create([
            'program_id' => $otherProgram->id, 'name' => 'Other OJT', 'year_level' => 4,
            'academic_period_id' => $current->id, 'is_active' => true,
        ]);
        $otherStudent = Student::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'student_id' => '2026-3002', 'first_name' => 'Other', 'last_name' => 'College',
            'gender' => 'female', 'birth_date' => '2002-01-01', 'address' => 'Santa Cruz',
            'contact_number' => '09171111112', 'college_id' => $otherCollege->id,
            'program_id' => $otherProgram->id, 'academic_period_id' => $current->id,
            'year_level' => 4, 'section' => 'A', 'registration_status' => 'approved',
            'internship_status' => 'active',
        ]);
        $otherStudent->currentInternship()->firstOrFail()->update(['academic_section_id' => $otherSection->id]);
        foreach (['2026-07-20', '2026-07-21'] as $date) {
            $day = Attendance::create([
                'student_id' => $student->id, 'student_internship_id' => $internship->id,
                'date' => $date, 'status' => 'present', 'work_mode' => 'wfo', 'session_type' => 'full_day',
                'am_time_in' => $date.' 08:00:00', 'am_time_out' => $date.' 12:00:00',
                'pm_time_in' => $date.' 13:00:00', 'pm_time_out' => $date.' 17:00:00',
            ]);
            if ($date === '2026-07-20') {
                AttendanceReview::create(['attendance_id' => $day->id, 'review_type' => 'verification',
                    'decision' => 'approved']);
            }
        }
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $student->college_id]);
        Sanctum::actingAs($dean->fresh());
        $this->getJson('/api/dean/dashboard')->assertOk()
            ->assertJsonPath('summary.total_students', 1)
            ->assertJsonPath('summary.active_interns', 1)
            ->assertJsonPath('charts.program_progress.0.average_progress', 100)
            ->assertJsonPath('charts.monthly_attendance.0.days', 1)
            ->assertJsonCount(1, 'charts.internship_status');
        $this->getJson('/api/dean/dashboard?academic_period_id='.$oldPeriod->id)->assertOk()
            ->assertJsonPath('summary.total_students', 1)
            ->assertJsonPath('summary.active_interns', 0)
            ->assertJsonPath('filters.can_review', false)
            ->assertJsonCount(0, 'charts.monthly_attendance');
        $this->getJson('/api/dean/dashboard?program_id='.$otherProgram->id)->assertStatus(422);
        Sanctum::actingAs(User::factory()->create(['role' => 'vpaa']));
        $this->getJson('/api/dean/dashboard')->assertForbidden();
    }

    public function test_dean_dashboard_pending_moa_count_tracks_approval_and_program_filter(): void
    {
        [, $student] = $this->context();
        $this->academicPeriod()->update(['status' => 'active']);
        $program = Program::findOrFail($student->program_id);
        $second = Program::create(['college_id' => $program->college_id, 'name' => 'Computer Science', 'code' => 'BSCS', 'is_active' => true]);
        $otherCollege = College::create(['name' => 'Education', 'code' => 'CED', 'is_active' => true]);
        $outside = Program::create(['college_id' => $otherCollege->id, 'name' => 'Education', 'code' => 'BSED', 'is_active' => true]);
        foreach ([$program, $second, $outside] as $item) {
            MOA::create(['hte_id' => $student->hte_id, 'program_id' => $item->id,
                'file_path' => 'test.pdf', 'effective_date' => '2026-01-01',
                'expiration_date' => '2027-12-31', 'status' => 'pending', 'program_status' => 'endorsed']);
        }
        Sanctum::actingAs(User::factory()->create(['role' => 'dean', 'college_id' => $program->college_id])->fresh());
        $this->getJson('/api/dean/dashboard')->assertOk()->assertJsonPath('summary.pending_moas', 2);
        $this->getJson('/api/dean/dashboard?program_id='.$program->id)->assertOk()
            ->assertJsonPath('summary.pending_moas', 1);
        MOA::query()->where('program_id', $program->id)->update(['status' => 'approved']);
        $this->getJson('/api/dean/dashboard')->assertOk()->assertJsonPath('summary.pending_moas', 1);
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
            ->assertJsonPath('template.file_name', 'Official Daily Journal.docx')
            ->assertJsonPath('template.uploaded_by', $coordinator->name);
        $html = $response->json('html');
        $this->assertStringContainsString('OFFICIAL DAILY JOURNAL FORM', $html);
        $this->assertStringContainsString('8:00 AM', $html);
        $this->assertStringContainsString('12:00 PM', $html);
        $this->assertStringContainsString('Prepared the official morning report.', $html);
        $this->assertStringContainsString('Updated the afternoon internship records.', $html);
        $this->assertSame(2, substr_count($html, '<img src="data:image/png;base64,'));
        $this->assertStringContainsString('MARINDUQUE STATE UNIVERSITY', $html);
        $this->assertStringContainsString('CICS GOAL', $html);
        $this->assertTrue(strpos($html, '<header class="letterhead header">') < strpos($html, '<section class="docx-body">'));
        $this->assertTrue(strpos($html, '<section class="docx-body">') < strpos($html, '<footer class="letterhead footer">'));

        $template = ReportTemplate::query()->firstOrFail();
        $reportHtml = app(ReportTemplateService::class)->renderHtml(
            Storage::disk('local')->path($template->file_path),
            ['report_title' => 'Student Internship Report'],
            [],
            ['Student Name'],
        );
        $this->assertSame(2, substr_count($reportHtml, '<img src="data:image/png;base64,'));
        $this->assertStringContainsString('MARINDUQUE STATE UNIVERSITY', $reportHtml);
        $this->assertStringContainsString('CICS GOAL', $reportHtml);
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
            'name' => 'Partner Company',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_number' => '09170000000',
            'is_active' => true,
        ]);
        $hte->programs()->attach($program->id, [
            'linked_by' => $coordinator->id,
            'is_active' => true,
        ]);
        $period = $this->academicPeriod();
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
            'academic_period_id' => $period->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09172222222',
            'hte_id' => $hte->id,
            'registration_status' => 'approved',
            'internship_status' => 'active',
        ]);
        \Illuminate\Support\Facades\DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $student->academic_section_id, 'coordinator_id' => $coordinator->id,
            'created_at' => now(), 'updated_at' => now(),
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
        $zip->addFromString('word/header1.xml', '<?xml version="1.0" encoding="UTF-8"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><w:p><w:r><w:drawing><wp:anchor><wp:positionH><wp:posOffset>0</wp:posOffset></wp:positionH><a:blip r:embed="rId1"/></wp:anchor></w:drawing></w:r><w:r><w:drawing><wp:anchor><wp:positionH><wp:posOffset>1000000</wp:posOffset></wp:positionH><a:blip r:embed="rId2"/></wp:anchor></w:drawing></w:r><w:r><w:t>Republic of the Philippines</w:t></w:r></w:p><w:p><w:r><w:t>MARINDUQUE STATE UNIVERSITY</w:t></w:r></w:p></w:hdr>');
        $zip->addFromString('word/_rels/header1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo1.png"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo2.png"/></Relationships>');
        $logo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZhpIAAAAASUVORK5CYII=');
        $zip->addFromString('word/media/logo1.png', $logo);
        $zip->addFromString('word/media/logo2.png', $logo);
        $zip->addFromString('word/footer1.xml', '<?xml version="1.0" encoding="UTF-8"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>CICS GOAL</w:t></w:r></w:p><w:p><w:r><w:t>Empowering minds</w:t></w:r></w:p></w:ftr>');
        $zip->close();

        return new UploadedFile($path, 'Official Daily Journal.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }
}
