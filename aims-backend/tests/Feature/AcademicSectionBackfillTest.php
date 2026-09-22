<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\College;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicSectionBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_students_are_safely_linked_to_their_academic_sections(): void
    {
        $college = College::create([
            'name' => 'College of Information and Computing Sciences',
            'code' => 'CICS',
            'is_active' => true,
        ]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Systems',
            'code' => 'BSIS',
            'is_active' => true,
        ]);
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027',
            'semester' => '2nd Semester',
            'start_date' => '2027-01-01',
            'end_date' => '2027-05-31',
            'status' => 'active',
        ]);
        $sectionA = AcademicSection::create([
            'program_id' => $program->id,
            'academic_period_id' => $period->id,
            'name' => 'BSIS A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $sectionB = AcademicSection::create([
            'program_id' => $program->id,
            'academic_period_id' => $period->id,
            'name' => 'BSIS B',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);

        $studentFromEnrollment = $this->createLegacyStudent($college, $program, '2026-001', 'A', $sectionA->id);
        $studentFromUniqueName = $this->createLegacyStudent($college, $program, '2026-002', 'BSIS B', $sectionB->id);

        $migration = require database_path('migrations/2026_08_18_000001_backfill_legacy_academic_section_ids.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('ojt_enrollments', [
            'student_record_id' => $studentFromEnrollment->id,
            'academic_section_id' => $sectionA->id,
        ]);
        $this->assertDatabaseHas('student_internships', [
            'student_id' => $studentFromUniqueName->id,
            'academic_section_id' => $sectionB->id,
        ]);
    }

    private function createLegacyStudent(College $college, Program $program, string $studentId, string $section, int $academicSectionId): Student
    {
        $user = User::factory()->create(['role' => 'student']);

        return Student::create([
            'user_id' => $user->id,
            'student_id' => $studentId,
            'first_name' => 'Legacy',
            'last_name' => $studentId,
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'contact_number' => '09170000000',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $academicSectionId,
            'year_level' => 4,
            'section' => $section,
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09171111111',
            'internship_year' => '2026-2027',
        ]);
    }
}
