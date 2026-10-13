<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramScopedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_head_attention_uses_internship_timeline_and_searches_full_names(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 12:00:00'));
        [$college, $program] = $this->academicContext();
        $programHead = User::factory()->create([
            'role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id,
        ]);
        $newIntern = $this->student($college, $program, 'NEW-001');
        $newIntern->update(['first_name' => 'Ralph Joseph', 'middle_name' => 'Paralejas', 'last_name' => 'Grimaldo']);
        $newIntern->currentInternship()->first()->update([
            'start_date' => '2026-09-27', 'end_date' => '2026-12-01',
        ]);
        $delayedIntern = $this->student($college, $program, 'LATE-001');
        $delayedIntern->currentInternship()->first()->update([
            'start_date' => '2026-08-01', 'end_date' => '2026-10-01',
        ]);

        Sanctum::actingAs($programHead);

        $this->getJson('/api/program-head/students')
            ->assertOk()
            ->assertJsonPath('summary.needs_attention', 1);
        $this->getJson('/api/program-head/students?search=Ralph%20Paralejas%20Grimaldo')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.needs_attention', 0)
            ->assertJsonPath('students.0.student_id', 'NEW-001');
    }

    public function test_program_head_near_completion_only_counts_active_interns(): void
    {
        [$college, $program] = $this->academicContext();
        $programHead = User::factory()->create([
            'role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id,
        ]);
        foreach (['active', 'dropped'] as $status) {
            $student = $this->student($college, $program, strtoupper($status).'-001');
            $student->currentInternship()->first()->update(['required_hours' => 10, 'status' => $status]);
            Attendance::create([
                'student_id' => $student->id,
                'date' => '2026-09-25',
                'status' => 'present',
                'work_mode' => 'wfo',
                'session_type' => 'full_day',
                'am_time_in' => '2026-09-25 08:00:00',
                'am_time_out' => '2026-09-25 12:00:00',
                'pm_time_in' => '2026-09-25 13:00:00',
                'pm_time_out' => '2026-09-25 17:00:00',
            ]);
        }

        Sanctum::actingAs($programHead);

        $this->getJson('/api/program-head/students')
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.near_completion', 1);
    }

    public function test_coordinator_only_sees_and_manages_students_in_the_assigned_program(): void
    {
        [$college, $bsis, $bsit] = $this->academicContext();
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $bsis->id,
        ]);
        $ownStudent = $this->student($college, $bsis, '2026-BSIS-01');
        $this->assignSection($coordinator, $ownStudent->academic_section_id);
        $otherStudent = $this->student($college, $bsit, '2026-BSIT-01');
        $ownAttendance = Attendance::create([
            'student_id' => $ownStudent->id,
            'date' => '2026-07-20',
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
        ]);
        $otherAttendance = Attendance::create([
            'student_id' => $otherStudent->id,
            'date' => '2026-07-20',
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
        ]);

        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/students')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('data.0.student_id', '2026-BSIS-01')
            ->assertJsonMissing(['student_id' => '2026-BSIT-01']);

        $this->getJson('/api/coordinator/students/'.$otherStudent->id)->assertNotFound();

        $this->getJson('/api/coordinator/attendance')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('data.0.id', $ownAttendance->id);

        $this->getJson('/api/coordinator/attendance/'.$otherAttendance->id)->assertNotFound();
    }

    public function test_coordinator_cannot_assign_a_student_to_another_programs_hte(): void
    {
        [$college, $bsis, $bsit] = $this->academicContext();
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $bsis->id,
        ]);
        $student = $this->student($college, $bsis, '2026-BSIS-03');
        $this->assignSection($coordinator, $student->academic_section_id);
        $otherHte = HTE::create([
            'name' => 'BSIT Partner',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_number' => '09170000001',
            'is_active' => true,
        ]);
        $otherHte->programs()->attach($bsit->id, ['is_active' => true]);
        Sanctum::actingAs($coordinator);

        $this->putJson("/api/coordinator/students/{$student->id}", [
            'student_id' => $student->student_id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->user->email,
            'gender' => $student->gender,
            'birth_date' => $student->birth_date->toDateString(),
            'address' => $student->address,
            'contact_number' => $student->contact_number,
            'college_id' => $college->id,
            'program_id' => $bsis->id,
            'year_level' => $student->year_level,
            'section' => $student->section,
            'parent_name' => $student->parent_name,
            'parent_address' => $student->parent_address,
            'parent_contact_number' => $student->parent_contact_number,
            'hte_id' => $otherHte->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('hte_id');

        $this->assertNull($student->fresh()->hte_id);
    }

    public function test_coordinator_reports_are_automatically_scoped_to_the_assigned_program(): void
    {
        [$college, $bsis, $bsit] = $this->academicContext();
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $bsis->id,
        ]);
        $ownStudent = $this->student($college, $bsis, '2026-BSIS-REPORT');
        $this->assignSection($coordinator, $ownStudent->academic_section_id);
        $otherStudent = $this->student($college, $bsit, '2026-BSIT-REPORT');

        Attendance::create([
            'student_id' => $ownStudent->id,
            'date' => '2026-08-17',
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
        ]);
        Attendance::create([
            'student_id' => $otherStudent->id,
            'date' => '2026-08-17',
            'status' => 'present',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
        ]);

        Sanctum::actingAs($coordinator);

        $this->getJson("/api/coordinator/reports?date_from=2026-08-17&date_to=2026-08-17&program_id={$bsit->id}")
            ->assertOk()
            ->assertJsonPath('program.id', $bsis->id)
            ->assertJsonPath('program.code', 'BSIS')
            ->assertJsonCount(1, 'students')
            ->assertJsonCount(1, 'attendance')
            ->assertJsonPath('students.0.studentId', '2026-BSIS-REPORT')
            ->assertJsonPath('attendance.0.studentId', '2026-BSIS-REPORT')
            ->assertJsonMissing(['studentId' => '2026-BSIT-REPORT']);
    }

    public function test_coordinator_and_program_head_reports_can_filter_their_program_by_section(): void
    {
        [$college, $bsis, $bsit] = $this->academicContext();
        $period = $this->academicPeriod();
        $sectionA = AcademicSection::create([
            'program_id' => $bsis->id,
            'academic_period_id' => $period->id,
            'name' => '4A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $sectionB = AcademicSection::create([
            'program_id' => $bsis->id,
            'academic_period_id' => $period->id,
            'name' => '4B',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $otherProgramSection = AcademicSection::create([
            'program_id' => $bsit->id,
            'academic_period_id' => $period->id,
            'name' => '4A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $sectionAStudent = $this->student($college, $bsis, '2026-BSIS-4A');
        $sectionAStudent->update(['academic_section_id' => $sectionA->id, 'section' => '4A']);
        $sectionBStudent = $this->student($college, $bsis, '2026-BSIS-4B');
        $sectionBStudent->update(['academic_section_id' => $sectionB->id, 'section' => '4B']);
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $bsis->id,
        ]);
        $programHead = User::factory()->create([
            'role' => 'program_head',
            'college_id' => $college->id,
            'program_id' => $bsis->id,
        ]);
        $this->assignSection($coordinator, $sectionA->id);
        $this->assignSection($coordinator, $sectionB->id);

        Sanctum::actingAs($coordinator);
        $this->getJson("/api/coordinator/reports?academic_section_id={$sectionA->id}")
            ->assertOk()
            ->assertJsonCount(2, 'sections')
            ->assertJsonPath('selected_section.id', $sectionA->id)
            ->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.studentId', '2026-BSIS-4A');
        $this->getJson("/api/coordinator/reports?academic_section_id={$otherProgramSection->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('academic_section_id');

        Sanctum::actingAs($programHead);
        $this->getJson("/api/program-head/dashboard?academic_section_id={$sectionB->id}")
            ->assertOk()
            ->assertJsonPath('scope.selected_section.id', $sectionB->id)
            ->assertJsonPath('overview.total_students', 1)
            ->assertJsonPath('analytics.section_performance.0.section', '4B');
        $this->getJson("/api/program-head/students?academic_section_id={$sectionA->id}")
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('students.0.student_id', '2026-BSIS-4A');
    }

    public function test_program_head_only_sees_and_reviews_documents_from_the_assigned_program(): void
    {
        [$college, $bsis, $bsit] = $this->academicContext();
        $programHead = User::factory()->create([
            'role' => 'program_head',
            'college_id' => $college->id,
            'program_id' => $bsis->id,
        ]);
        $ownStudent = $this->student($college, $bsis, '2026-BSIS-02');
        $otherStudent = $this->student($college, $bsit, '2026-BSIT-02');
        InternshipRequirement::create([
            'student_id' => $ownStudent->id,
            'requirement_name' => 'Curriculum Vitae',
            'file_path' => 'requirements/own.pdf',
        ]);
        $otherRequirement = InternshipRequirement::create([
            'student_id' => $otherStudent->id,
            'requirement_name' => 'Curriculum Vitae',
            'file_path' => 'requirements/other.pdf',
        ]);

        Sanctum::actingAs($programHead);

        $this->getJson('/api/program-head/students')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('students.0.student_id', '2026-BSIS-02')
            ->assertJsonMissing(['student_id' => '2026-BSIT-02']);

        $this->getJson('/api/program-head/documents')
            ->assertOk()
            ->assertJsonPath('summary.submitted_requirements', 1);

        $this->postJson('/api/program-head/documents/requirements/'.$otherRequirement->id.'/review', [
            'decision' => 'approved',
        ])->assertNotFound();
    }

    public function test_unassigned_program_staff_receive_a_clear_forbidden_response(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'No academic program is assigned to this account. Ask an administrator to assign a college and program.');
    }

    public function test_admin_must_assign_coordinators_and_program_heads_to_a_valid_program(): void
    {
        [$college, $bsis] = $this->academicContext();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users', [
            'name' => 'BSIS Coordinator',
            'email' => 'bsis.coordinator@example.com',
            'password' => 'password123',
            'role' => 'coordinator',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['college_id', 'program_id']);

        $this->postJson('/api/admin/users', [
            'name' => 'BSIS Coordinator',
            'email' => 'bsis.coordinator@example.com',
            'password' => 'password123',
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $bsis->id,
        ])->assertCreated()
            ->assertJsonPath('user.college_id', $college->id)
            ->assertJsonPath('user.program_id', $bsis->id)
            ->assertJsonPath('user.program.code', 'BSIS');
    }

    private function academicContext(): array
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $bsis = Program::create(['college_id' => $college->id, 'name' => 'Information Systems', 'code' => 'BSIS', 'is_active' => true]);
        $bsit = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);

        return [$college, $bsis, $bsit];
    }

    private function assignSection(User $coordinator, int $sectionId): void
    {
        \Illuminate\Support\Facades\DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $sectionId, 'coordinator_id' => $coordinator->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function student(College $college, Program $program, string $schoolId): Student
    {
        $user = User::factory()->create(['role' => 'student']);
        $period = AcademicPeriod::firstOrCreate(
            ['academic_year' => '2026-2027', 'semester' => '1st Semester'],
            ['start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'status' => 'active'],
        );
        $section = AcademicSection::where('program_id', $program->id)->first()
            ?? AcademicSection::create([
                'program_id' => $program->id,
                'academic_period_id' => $period->id,
                'name' => '4-'.$program->code.' A',
                'year_level' => 4,
                'academic_year' => $period->academic_year,
                'is_active' => true,
            ]);

        return Student::create([
            'user_id' => $user->id,
            'student_id' => $schoolId,
            'first_name' => 'Test',
            'last_name' => $program->code,
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'contact_number' => '09170000000',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $section->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09171111111',
            'registration_status' => 'approved',
            'internship_status' => 'active',
        ]);
    }
}
