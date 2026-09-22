<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\College;
use App\Models\Evaluation;
use App\Models\HTE;
use App\Models\HteDepartment;
use App\Models\HteDepartmentProgram;
use App\Models\InternshipRequirement;
use App\Models\InternshipTask;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_guardian_academics_and_internship_are_normalized_without_changing_student_updates(): void
    {
        $college = College::create([
            'name' => 'College of Information and Computing Sciences',
            'code' => 'CICS',
            'is_active' => true,
        ]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Bachelor of Science in Information Systems',
            'code' => 'BSIS',
            'is_active' => true,
        ]);
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);
        $section = AcademicSection::create([
            'program_id' => $program->id,
            'academic_period_id' => $period->id,
            'name' => '4-BSIS A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ]);
        $enrollment = OjtEnrollment::create([
            'school_id' => '2026-001',
            'full_name' => 'DELA CRUZ, Juan',
            'section' => '4-BSIS A',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $section->id,
            'academic_period_id' => $period->id,
            'source' => 'manual',
            'added_by' => $coordinator->id,
        ]);
        $studentUser = User::factory()->create(['role' => 'student']);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'gender' => 'male',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $section->id,
            'academic_period_id' => $period->id,
            'year_level' => 4,
            'section' => '4-BSIS A',
            'parent_name' => 'Maria Dela Cruz',
            'parent_relationship' => 'Mother',
            'parent_address' => 'Boac, Marinduque',
            'parent_contact_number' => '09171234567',
            'registration_status' => 'approved',
            'internship_status' => 'pending',
        ]);

        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->id,
            'full_name' => 'Maria Dela Cruz',
            'relationship' => 'Mother',
        ]);
        $this->assertDatabaseHas('student_internships', [
            'student_id' => $student->id,
            'ojt_enrollment_id' => $enrollment->id,
            'academic_section_id' => $section->id,
            'status' => 'pending',
            'is_current' => true,
        ]);

        $hte = HTE::create([
            'name' => 'Marinduque State University',
            'normalized_name' => 'marinduque state university',
            'address' => 'Boac, Marinduque',
            'contact_person' => 'HTE Contact',
            'contact_email' => 'hte@example.com',
            'contact_number' => '09170000000',
            'is_active' => true,
        ]);
        $hte->programs()->attach($program->id, [
            'linked_by' => $coordinator->id,
            'is_active' => true,
        ]);
        $department = HteDepartment::create([
            'hte_id' => $hte->id,
            'department_name' => 'Information Technology Office',
            'normalized_name' => 'information technology office',
        ]);
        $department->programs()->attach($program->id, [
            'linked_by' => $coordinator->id,
            'is_active' => true,
        ]);
        $departmentProgram = HteDepartmentProgram::where('hte_department_id', $department->id)
            ->where('program_id', $program->id)
            ->firstOrFail();
        $supervisor = Supervisor::create([
            'hte_department_id' => $department->id,
            'full_name' => 'Joe Supervisor',
            'normalized_name' => 'joe supervisor',
        ]);

        // Existing coordinator updates continue to write the legacy fields while
        // the normalized current-internship record is synchronized automatically.
        $student->update([
            'hte_id' => $hte->id,
            'hte_department_id' => $department->id,
            'supervisor_record_id' => $supervisor->id,
            'internship_status' => 'active',
            'schedule_status' => 'approved',
            'ojt_start_date' => '2026-08-10',
            'ojt_end_date' => '2026-12-15',
            'required_ojt_hours' => 486,
            'official_am_start' => '07:30',
            'official_am_end' => '11:30',
            'official_pm_start' => '13:00',
            'official_pm_end' => '16:30',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ]);

        $internship = $student->fresh()->currentInternship()->firstOrFail();
        $this->assertSame($section->id, $internship->academic_section_id);
        $this->assertSame('BSIS', $internship->academicSection->program->code);
        $this->assertSame('CICS', $internship->academicSection->program->college->code);
        $this->assertSame($hte->id, $internship->hte_id);
        $this->assertSame($department->id, $internship->hte_department_id);
        $this->assertSame($departmentProgram->id, $internship->hte_department_program_id);
        $this->assertSame($hte->id, $internship->hteProgram->hte_id);
        $this->assertSame($supervisor->id, $internship->supervisor_record_id);
        $this->assertDatabaseHas('student_internship_placements', [
            'student_internship_id' => $internship->id,
            'hte_program_id' => null,
            'hte_department_program_id' => $departmentProgram->id,
            'supervisor_record_id' => $supervisor->id,
            'assigned_by' => $coordinator->id,
            'is_current' => true,
        ]);
        $this->assertSame('active', $internship->status);
        $this->assertSame('approved', $internship->deployment_status);
        $this->assertSame(1, $student->internships()->count());
        $this->assertSame(7, $internship->scheduleOverrides()->count());

        $this->assertDatabaseHas('ojt_enrollments', [
            'id' => $enrollment->id,
            'registration_status' => 'approved',
        ]);

        // Changing from a department assignment to an HTE-only assignment keeps
        // the old placement as history and exposes the new destination virtually.
        $student->update([
            'hte_id' => $hte->id,
            'hte_department_id' => null,
            'supervisor_record_id' => null,
        ]);
        $internship = $student->fresh()->currentInternship()->firstOrFail();
        $this->assertSame($hte->id, $internship->hte_id);
        $this->assertNull($internship->hte_department_id);
        $this->assertNull($internship->supervisor_record_id);
        $this->assertSame(2, $internship->placements()->count());
        $this->assertDatabaseHas('student_internship_placements', [
            'student_internship_id' => $internship->id,
            'hte_department_program_id' => $departmentProgram->id,
            'supervisor_record_id' => $supervisor->id,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('student_internship_placements', [
            'student_internship_id' => $internship->id,
            'hte_program_id' => $internship->hte_program_id,
            'hte_department_program_id' => null,
            'supervisor_record_id' => null,
            'is_current' => true,
        ]);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => '2026-08-10',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
            'status' => 'present',
        ]);
        $requirement = InternshipRequirement::create([
            'student_id' => $student->id,
            'requirement_name' => 'Curriculum Vitae',
        ]);
        $task = InternshipTask::create([
            'student_id' => $student->id,
            'supervisor_id' => $coordinator->id,
            'title' => 'Configure a workstation',
        ]);
        $evaluation = Evaluation::create([
            'student_id' => $student->id,
            'supervisor_id' => $coordinator->id,
            'evaluation_type' => 'midterm',
            'work_quality' => 5,
            'communication' => 5,
            'professionalism' => 5,
            'attendance' => 5,
            'technical_skills' => 5,
            'teamwork' => 5,
        ]);

        foreach ([$attendance, $requirement, $task, $evaluation] as $record) {
            $this->assertSame($internship->id, $record->student_internship_id);
        }

        $otherStudent = Student::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'student_id' => '2026-002',
            'first_name' => 'Other',
            'last_name' => 'Student',
            'section' => '4-BSIS A',
        ]);

        $this->expectException(\LogicException::class);
        Attendance::create([
            'student_id' => $otherStudent->id,
            'student_internship_id' => $internship->id,
            'date' => '2026-08-11',
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
            'status' => 'present',
        ]);
    }
}
