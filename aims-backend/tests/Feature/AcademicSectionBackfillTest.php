<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\College;
use App\Models\OjtEnrollment;
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
        $sectionA = AcademicSection::create([
            'program_id' => $program->id,
            'name' => 'BSIS A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $sectionB = AcademicSection::create([
            'program_id' => $program->id,
            'name' => 'BSIS B',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);

        $studentFromEnrollment = $this->createLegacyStudent($college, $program, '2026-001', 'A');
        OjtEnrollment::create([
            'school_id' => $studentFromEnrollment->student_id,
            'full_name' => 'Enrollment Linked Student',
            'section' => 'A',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $sectionA->id,
            'source' => 'manual',
            'student_record_id' => $studentFromEnrollment->id,
            'registered_at' => now(),
        ]);

        $studentFromUniqueName = $this->createLegacyStudent($college, $program, '2026-002', 'BSIS B');

        $migration = require database_path('migrations/2026_08_18_000001_backfill_legacy_academic_section_ids.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('students', [
            'id' => $studentFromEnrollment->id,
            'academic_section_id' => $sectionA->id,
            'section' => 'BSIS A',
        ]);
        $this->assertDatabaseHas('ojt_enrollments', [
            'student_record_id' => $studentFromEnrollment->id,
            'academic_section_id' => $sectionA->id,
            'section' => 'BSIS A',
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $studentFromUniqueName->id,
            'academic_section_id' => $sectionB->id,
            'section' => 'BSIS B',
        ]);
    }

    private function createLegacyStudent(College $college, Program $program, string $studentId, string $section): Student
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
            'academic_section_id' => null,
            'year_level' => 4,
            'section' => $section,
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09171111111',
            'internship_year' => '2026-2027',
        ]);
    }
}
