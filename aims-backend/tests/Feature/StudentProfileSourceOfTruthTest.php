<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\College;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class StudentProfileSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_updates_are_visible_through_the_user_account(): void
    {
        [$user, $student] = $this->studentAccount();

        $student->update([
            'first_name' => 'Maria',
            'middle_name' => 'Reyes',
            'last_name' => 'Santos',
            'contact_number' => '09991234567',
            'address' => 'Quezon City',
        ]);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'college_id' => $student->college_id,
            'program_id' => $student->program_id,
            'first_name' => 'Maria',
            'middle_name' => 'Reyes',
            'last_name' => 'Santos',
            'contact_number' => '09991234567',
            'address' => 'Quezon City',
        ]);
        $this->assertSame('Maria Reyes Santos', $user->fresh()->name);
        $this->assertSame('09991234567', $user->fresh()->contact_number);
        $this->assertSame('Quezon City', $user->fresh()->address);
    }

    public function test_enrollment_import_identity_remains_an_immutable_snapshot(): void
    {
        [, $student, $section] = $this->studentAccount();
        $enrollment = OjtEnrollment::create([
            'school_id' => $student->student_id,
            'full_name' => 'SANTOS, Maria Reyes',
            'academic_section_id' => $section->id,
            'source' => 'csv',
            'student_record_id' => $student->id,
        ]);

        $student->update([
            'first_name' => 'Maria Isabel',
            'contact_number' => '09990000000',
        ]);

        $this->assertDatabaseHas('ojt_enrollments', [
            'id' => $enrollment->id,
            'school_id' => $student->student_id,
            'full_name' => 'SANTOS, Maria Reyes',
            'source' => 'csv',
        ]);

        $this->expectException(LogicException::class);
        $enrollment->update(['full_name' => 'Changed imported name']);
    }

    public function test_enrollment_cannot_link_to_a_different_school_id(): void
    {
        [, $student, $section] = $this->studentAccount();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('school ID does not match');

        OjtEnrollment::create([
            'school_id' => 'DIFFERENT-ID',
            'full_name' => 'Imported Student',
            'academic_section_id' => $section->id,
            'source' => 'csv',
            'student_record_id' => $student->id,
        ]);
    }

    public function test_employee_account_is_synchronized_to_normalized_profile_and_assignment(): void
    {
        $college = College::create([
            'name' => 'College of Information and Computing Sciences',
            'code' => 'CICS',
            'required_ojt_hours' => 486,
            'is_active' => true,
        ]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Systems',
            'code' => 'BSIS',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'coordinator',
            'name' => 'Normalized Coordinator',
            'contact_number' => '09171234567',
            'address' => 'Boac, Marinduque',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ]);

        $this->assertDatabaseHas('roles', ['id' => $user->role_id, 'code' => 'coordinator']);
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $user->id,
            'college_id' => $college->id,
            'program_id' => $program->id,
            'first_name' => 'Normalized',
            'middle_name' => null,
            'last_name' => 'Coordinator',
            'contact_number' => '09171234567',
            'address' => 'Boac, Marinduque',
        ]);
        $this->assertDatabaseHas('employee_program_assignments', [
            'employee_profile_id' => $user->employeeProfile()->value('id'),
            'program_id' => $program->id,
            'is_active' => true,
        ]);
        $this->assertSame($program->id, $user->normalizedProgramId());
        $this->assertSame($college->id, $user->normalizedCollegeId());
        $this->assertSame($program->id, $user->fresh()->normalizedProgramId());
        $this->assertSame($college->id, $user->fresh()->normalizedCollegeId());
    }

    public function test_student_academic_foreign_keys_follow_section_and_program_changes(): void
    {
        [, $student, $firstSection] = $this->studentAccount();
        $student->update(['academic_section_id' => $firstSection->id]);

        $newCollege = College::create([
            'name' => 'Business', 'code' => 'BUS', 'required_ojt_hours' => 486, 'is_active' => true,
        ]);
        $newProgram = Program::create([
            'college_id' => $newCollege->id, 'name' => 'Management', 'code' => 'BSM', 'is_active' => true,
        ]);
        $newSection = AcademicSection::create([
            'academic_period_id' => $firstSection->academic_period_id,
            'program_id' => $newProgram->id,
            'name' => '4B', 'year_level' => 4, 'is_active' => true,
        ]);

        $student->update(['academic_section_id' => $newSection->id]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id, 'program_id' => $newProgram->id, 'college_id' => $newCollege->id,
        ]);

        $anotherCollege = College::create([
            'name' => 'Arts', 'code' => 'ART', 'required_ojt_hours' => 486, 'is_active' => true,
        ]);
        $newProgram->update(['college_id' => $anotherCollege->id]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id, 'program_id' => $newProgram->id, 'college_id' => $anotherCollege->id,
        ]);
    }

    public function test_employee_academic_foreign_keys_follow_assignment_changes(): void
    {
        $college = College::create([
            'name' => 'Computing', 'code' => 'CICS', 'required_ojt_hours' => 486, 'is_active' => true,
        ]);
        $firstProgram = Program::create([
            'college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true,
        ]);
        $secondProgram = Program::create([
            'college_id' => $college->id, 'name' => 'Information Systems', 'code' => 'BSIS', 'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $firstProgram->id,
        ]);

        $user->update(['program_id' => $secondProgram->id]);
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $user->id, 'program_id' => $secondProgram->id, 'college_id' => $college->id,
        ]);

        $user->update(['program_id' => null]);
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $user->id, 'program_id' => null, 'college_id' => null,
        ]);
    }

    public function test_same_student_can_have_another_ojt_enrollment_in_a_later_academic_period(): void
    {
        [, $student, $firstSection] = $this->studentAccount();
        $program = $firstSection->program;
        $secondPeriod = AcademicPeriod::create([
            'academic_year' => '2027-2028',
            'semester' => '2nd Semester',
            'start_date' => '2028-01-10',
            'end_date' => '2028-05-31',
            'status' => 'draft',
        ]);
        $secondSection = AcademicSection::create([
            'academic_period_id' => $secondPeriod->id,
            'program_id' => $program->id,
            'name' => '4A',
            'year_level' => 4,
            'is_active' => true,
        ]);

        foreach ([$firstSection, $secondSection] as $section) {
            OjtEnrollment::create([
                'school_id' => $student->student_id,
                'full_name' => $student->full_name,
                'academic_section_id' => $section->id,
                'source' => 'manual',
                'student_record_id' => $student->id,
            ]);
        }

        $this->assertSame(2, OjtEnrollment::where('student_record_id', $student->id)->count());
    }

    /** @return array{User, Student, AcademicSection} */
    private function studentAccount(): array
    {
        $college = College::create([
            'name' => 'Computing',
            'code' => fake()->unique()->bothify('C##'),
            'required_ojt_hours' => 486,
            'is_active' => true,
        ]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => fake()->unique()->bothify('P##'),
            'is_active' => true,
        ]);
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
        ]);
        $section = AcademicSection::create([
            'academic_period_id' => $period->id,
            'program_id' => $program->id,
            'name' => '4A',
            'year_level' => 4,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'student',
            'name' => 'Stale Display Name',
            'contact_number' => '09170000000',
            'address' => 'Old Address',
        ]);
        $student = Student::withoutEvents(fn () => Student::create([
            'user_id' => $user->id,
            'student_id' => fake()->unique()->bothify('2026-####'),
            'first_name' => 'Initial',
            'last_name' => 'Student',
            'contact_number' => '09171111111',
            'address' => 'Initial Address',
        ]));
        $student->setAttribute('academic_section_id', $section->id);

        return [$user, $student, $section];
    }
}
