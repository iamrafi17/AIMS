<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\ApprovalRecord;
use App\Models\Attendance;
use App\Models\College;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Support\SmartClockAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramSmartClockControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_switch_and_coordinator_consent_control_clock_without_affecting_other_programs(): void
    {
        Storage::fake('local');
        [$program, $coordinator, $student] = $this->context('CICS', 'BSIS', 'BSIS-001');
        [$otherProgram, $otherCoordinator, $otherStudent] = $this->context('CBA', 'BSTM', 'BSTM-001');

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()->assertJsonPath('clock_disabled', true)
            ->assertJsonPath('access.program_enabled', false);

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinator/attendance/smart-clock-control')
            ->assertOk()->assertJsonPath('enabled', false);
        $this->putJson('/api/coordinator/attendance/smart-clock-control', ['enabled' => true])
            ->assertOk()->assertJsonPath('program_id', $program->id)->assertJsonPath('enabled', true);
        $this->assertFalse($otherProgram->fresh()->smart_clock_enabled);

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()->assertJsonPath('access.available', false)
            ->assertJsonPath('access.consent_accepted', false);
        $this->postJson('/api/student/attendance/quick-clock', ['status' => 'absent'])
            ->assertForbidden();

        $student->update(['consent_status' => 'done']);
        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()->assertJsonPath('access.consent_accepted', false);

        InternshipRequirement::ensureForStudent($student->id);
        $consent = InternshipRequirement::where('student_id', $student->id)
            ->where('requirement_name', "Notarized Parent's/Guardian's Consent")
            ->firstOrFail();
        $this->post('/api/student/requirements/'.$consent->id.'/upload', [
            'file' => UploadedFile::fake()->create('consent.pdf', 1, 'application/pdf'),
        ])->assertOk();

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/students/{$student->id}/requirements/{$consent->id}/review", [
            'decision' => 'approved',
        ])->assertOk();
        $this->assertSame('done', $student->fresh()->consent_status);

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()->assertJsonPath('access.available', true);
        $this->postJson('/api/student/attendance/quick-clock', ['status' => 'absent'])
            ->assertOk()->assertJsonPath('attendance.status', 'absent');

        Sanctum::actingAs($coordinator);
        $this->putJson('/api/coordinator/attendance/smart-clock-control', ['enabled' => false])
            ->assertOk()->assertJsonPath('enabled', false);
        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()->assertJsonPath('access.available', false);
        $this->postJson('/api/student/attendance/quick-clock', ['status' => 'absent'])
            ->assertForbidden();

        Sanctum::actingAs($otherStudent->user);
        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()->assertJsonPath('access.program_enabled', false);

        Sanctum::actingAs($otherCoordinator);
        $this->getJson('/api/coordinator/attendance/smart-clock-control')
            ->assertOk()->assertJsonPath('program_id', $otherProgram->id)
            ->assertJsonPath('enabled', false);
        $this->putJson('/api/coordinator/attendance/smart-clock-control', ['enabled' => true])
            ->assertOk()->assertJsonPath('enabled', true);
        $this->assertFalse($program->fresh()->smart_clock_enabled);
    }

    public function test_coordinator_accepted_consent_enables_smart_clock_without_individual_vpaa_approval(): void
    {
        Storage::fake('local');
        [$program, $coordinator, $student] = $this->context('CICS', 'BSIS', 'BSIS-002');
        $programHead = User::factory()->create([
            'role' => 'program_head', 'college_id' => $program->college_id, 'program_id' => $program->id,
        ]);
        InternshipRequirement::ensureForStudent($student->id);
        $consent = InternshipRequirement::where('student_id', $student->id)
            ->where('requirement_name', "Notarized Parent's/Guardian's Consent")
            ->firstOrFail();

        Sanctum::actingAs($student->user);
        $this->post('/api/student/requirements/'.$consent->id.'/upload', [
            'file' => UploadedFile::fake()->create('consent.pdf', 1, 'application/pdf'),
        ])->assertOk();

        Sanctum::actingAs($programHead);
        $this->postJson("/api/program-head/documents/requirements/{$consent->id}/review", [
            'decision' => 'approved',
        ])->assertUnprocessable();

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/students/{$student->id}/requirements/{$consent->id}/review", [
            'decision' => 'approved',
        ])->assertOk();
        $program->update(['smart_clock_enabled' => true]);

        Sanctum::actingAs($programHead);
        $this->postJson("/api/program-head/documents/requirements/{$consent->id}/review", [
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('requirement.program_head_endorsed', false);
        $this->assertDatabaseMissing('approval_records', [
            'subject_type' => ApprovalRecord::TYPE_DOCUMENT,
            'subject_id' => $consent->id,
        ]);

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()->assertJsonPath('access.available', true);
    }

    public function test_disabling_program_allows_only_an_active_time_out_to_finish(): void
    {
        [$program, $coordinator, $student] = $this->context('CICS', 'BSIS', 'BSIS-003');
        $student->update(['consent_status' => 'done']);
        Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'work_mode' => 'wfh',
            'session_type' => 'am_half',
            'am_time_in' => now(),
        ]);

        $this->assertFalse(SmartClockAccess::status($student)['available']);
        $this->assertTrue(SmartClockAccess::status($student, true)['can_finish_session']);

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/attendance/quick-clock')
            ->assertOk()->assertJsonPath('access.available', true)
            ->assertJsonPath('access.can_finish_session', true);
        $this->postJson('/api/student/attendance/clock-in', [
            'latitude' => 13.4753, 'longitude' => 122.0275,
            'work_mode' => 'wfh', 'session_type' => 'am_half',
        ])->assertStatus(410)
            ->assertJsonPath('quick_clock_endpoint', '/api/student/attendance/quick-clock');
    }

    private function context(string $collegeCode, string $programCode, string $studentId): array
    {
        $college = College::create(['name' => $collegeCode, 'code' => $collegeCode, 'is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id, 'name' => $programCode, 'code' => $programCode, 'is_active' => true,
        ]);
        $section = AcademicSection::create([
            'program_id' => $program->id,
            'academic_period_id' => $this->academicPeriod()->id,
            'name' => '4A', 'year_level' => 4, 'academic_year' => '2026-2027', 'is_active' => true,
        ]);
        $coordinator = User::factory()->create([
            'role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id,
        ]);
        DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $section->id, 'coordinator_id' => $coordinator->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['role' => 'student']);
        $student = Student::create([
            'user_id' => $user->id, 'student_id' => $studentId,
            'first_name' => 'Test', 'last_name' => 'Intern',
            'gender' => 'female', 'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz', 'contact_number' => '09170000000',
            'college_id' => $college->id, 'program_id' => $program->id,
            'academic_section_id' => $section->id,
            'year_level' => 4, 'section' => '4A',
            'parent_name' => 'Parent', 'parent_relationship' => 'Parent',
            'parent_address' => 'Santa Cruz', 'parent_contact_number' => '09170000001',
            'internship_semester' => '2nd Semester', 'internship_year' => '2026-2027',
        ]);

        return [$program, $coordinator, $student];
    }
}
