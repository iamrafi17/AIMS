<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\College;
use App\Models\OjtEnrollment;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OjtEnrollmentNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_context_is_derived_from_its_section_for_every_college_and_program(): void
    {
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);
        $examples = [
            ['CICS', 'BSIS'],
            ['CGov', 'BAPolSci'],
            ['CBA', 'BSTM'],
            ['CoEd', 'BEED'],
        ];

        foreach ($examples as $index => [$collegeCode, $programCode]) {
            $college = College::create([
                'name' => $collegeCode.' College',
                'code' => $collegeCode,
                'is_active' => true,
            ]);
            $program = Program::create([
                'college_id' => $college->id,
                'name' => $programCode.' Program',
                'code' => $programCode,
                'is_active' => true,
            ]);
            $section = AcademicSection::create([
                'program_id' => $program->id,
                'academic_period_id' => $period->id,
                'name' => '4-'.$programCode.' A',
                'year_level' => 4,
                'academic_year' => $period->academic_year,
                'is_active' => true,
            ]);
            $enrollment = OjtEnrollment::create([
                'school_id' => '2026-NORM-'.($index + 1),
                'full_name' => 'Sample Student '.($index + 1),
                'academic_section_id' => $section->id,
                'source' => 'manual',
            ])->fresh();

            $this->assertSame($section->name, $enrollment->section);
            $this->assertSame($program->id, $enrollment->program_id);
            $this->assertSame($college->id, $enrollment->college_id);
            $this->assertSame($period->id, $enrollment->academic_period_id);
            $this->assertSame(1, OjtEnrollment::forProgram($program->id)->count());
            $this->assertSame(1, OjtEnrollment::forCollege($college->id)->count());
        }

        $this->assertSame(4, OjtEnrollment::forAcademicPeriod($period->id)->count());
    }

    public function test_legacy_context_cannot_disagree_with_the_selected_section(): void
    {
        $firstCollege = College::create(['name' => 'CICS', 'code' => 'CICS']);
        $secondCollege = College::create(['name' => 'CGov', 'code' => 'CGov']);
        $firstProgram = Program::create(['college_id' => $firstCollege->id, 'name' => 'BSIS', 'code' => 'BSIS']);
        $secondProgram = Program::create(['college_id' => $secondCollege->id, 'name' => 'BAPolSci', 'code' => 'BAPolSci']);
        $section = AcademicSection::create([
            'program_id' => $firstProgram->id,
            'academic_period_id' => $this->academicPeriod()->id,
            'name' => '4-BSIS A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The enrollment program_id does not match its academic section.');

        OjtEnrollment::create([
            'school_id' => '2026-MISMATCH',
            'full_name' => 'Wrong Program Student',
            'academic_section_id' => $section->id,
            'program_id' => $secondProgram->id,
            'source' => 'manual',
        ]);
    }
}
