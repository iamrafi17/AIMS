<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\ProgramRequirement;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_update_review_and_deactivate_a_registered_student_without_deleting_history(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $payload = $this->studentPayload($college, $program, $hte);
        $student = $this->createStudent($college, $program, $hte);

        $payload['first_name'] = 'Updated';
        $payload['email'] = 'updated.intern@example.com';

        $this->putJson('/api/coordinator/students/'.$student->id, $payload)
            ->assertOk()
            ->assertJsonPath('student.first_name', 'Updated')
            ->assertJsonPath('student.user.email', 'updated.intern@example.com');

        $this->postJson('/api/coordinator/students/'.$student->id.'/approve')
            ->assertOk();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'registration_status' => 'approved',
            'internship_status' => 'active',
            'registration_reviewed_by' => $coordinator->id,
        ]);

        $userId = $student->user_id;
        $this->deleteJson('/api/coordinator/students/'.$student->id)
            ->assertOk();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'internship_status' => 'dropped',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'is_active' => false,
        ]);
    }

    public function test_coordinator_can_manually_add_an_ojt_enrollment_without_credentials(): void
    {
        [$coordinator, , , , $section] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $this->postJson('/api/coordinator/students', [
            'full_name' => 'Juan Dela Cruz',
            'school_id' => '2026-QUICK-01',
            'academic_section_id' => $section->id,
        ])->assertCreated()
            ->assertJsonPath('enrollment.school_id', '2026-QUICK-01')
            ->assertJsonPath('enrollment.full_name', 'Juan Dela Cruz')
            ->assertJsonPath('enrollment.section', '4A')
            ->assertJsonMissingPath('temporary_password');

        $this->assertDatabaseHas('ojt_enrollments', [
            'school_id' => '2026-QUICK-01',
            'full_name' => 'Juan Dela Cruz',
            'section' => '4A',
            'academic_section_id' => $section->id,
            'source' => 'manual',
        ]);
        $this->assertDatabaseMissing('students', ['student_id' => '2026-QUICK-01']);
        $this->assertDatabaseMissing('users', ['name' => 'Juan Dela Cruz']);
    }

    public function test_coordinator_can_choose_the_ojt_enrollment_page_size(): void
    {
        [$coordinator, $college, $program] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        foreach (range(1, 11) as $number) {
            OjtEnrollment::create([
                'school_id' => '2026-PAGE-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'full_name' => 'Enrollment Student '.$number,
                'section' => 'BSIT 4A',
                'college_id' => $college->id,
                'program_id' => $program->id,
                'source' => 'manual',
                'added_by' => $coordinator->id,
            ]);
        }

        $this->getJson('/api/coordinator/students/enrollments?per_page=10&page=2')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 11)
            ->assertJsonCount(1, 'data');
    }

    public function test_coordinator_can_choose_the_student_list_page_size(): void
    {
        [$coordinator, $college, $program] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        foreach (range(1, 11) as $number) {
            $user = User::factory()->create(['role' => 'student']);
            Student::create([
                'user_id' => $user->id,
                'student_id' => '2026-STUDENT-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'first_name' => 'Paged',
                'last_name' => 'Student '.$number,
                'gender' => 'female',
                'birth_date' => '2002-01-01',
                'address' => 'Boac, Marinduque',
                'phone' => '09170000000',
                'college_id' => $college->id,
                'program_id' => $program->id,
                'year_level' => 4,
                'section' => 'A',
                'parent_name' => 'Test Parent',
                'parent_address' => 'Boac, Marinduque',
                'parent_phone' => '09171111111',
            ]);
        }

        $this->getJson('/api/coordinator/students?per_page=10&page=2')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 11)
            ->assertJsonCount(1, 'data');
    }

    public function test_coordinator_can_approve_and_reject_student_requirements(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        $requirement = InternshipRequirement::create([
            'student_id' => $student->id,
            'requirement_name' => 'Consent Form',
            'file_path' => 'requirements/consent.pdf',
            'file_type' => 'pdf',
        ]);
        Sanctum::actingAs($coordinator);

        $this->postJson("/api/coordinator/students/{$student->id}/requirements/{$requirement->id}/review", [
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('requirement.status', 'approved');

        $this->postJson("/api/coordinator/students/{$student->id}/requirements/{$requirement->id}/review", [
            'decision' => 'rejected',
            'feedback' => 'Please upload the signed copy.',
        ])->assertOk()->assertJsonPath('requirement.status', 'rejected');

        $this->assertDatabaseHas('internship_requirements', [
            'id' => $requirement->id,
            'status' => 'rejected',
            'feedback' => 'Please upload the signed copy.',
            'reviewed_by' => $coordinator->id,
        ]);
    }

    public function test_coordinator_can_view_the_complete_official_requirement_checklist(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/coordinator/students/'.$student->id)
            ->assertOk()
            ->assertJsonCount(10, 'requirements')
            ->assertJsonPath('progress.requirements_total', 10)
            ->assertJsonPath('progress.requirements_approved', 0)
            ->assertJsonPath('progress.requirements_percent', 0);

        foreach (InternshipRequirement::OFFICIAL_REQUIREMENTS as $requirementName) {
            $response->assertJsonFragment([
                'requirement_name' => $requirementName,
                'file_path' => null,
            ]);
        }
    }

    public function test_coordinator_can_customize_the_program_requirement_checklist(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/requirements')
            ->assertOk()
            ->assertJsonCount(10, 'requirements');

        $created = $this->postJson('/api/coordinator/requirements', [
            'name' => 'Barangay Clearance',
            'instructions' => 'Upload a clear, recently issued copy.',
        ])->assertCreated()
            ->assertJsonPath('requirement.name', 'Barangay Clearance')
            ->assertJsonPath('requirement.is_active', true);

        $definitionId = $created->json('requirement.id');
        $this->assertDatabaseHas('program_requirements', [
            'id' => $definitionId,
            'program_id' => $program->id,
            'name' => 'Barangay Clearance',
        ]);
        $this->assertDatabaseHas('internship_requirements', [
            'student_id' => $student->id,
            'program_requirement_id' => $definitionId,
            'requirement_name' => 'Barangay Clearance',
        ]);

        Sanctum::actingAs($student->user);
        $studentChecklist = $this->getJson('/api/student/requirements')
            ->assertOk()
            ->assertJsonCount(11)
            ->assertJsonFragment([
                'name' => 'Barangay Clearance',
                'instructions' => 'Upload a clear, recently issued copy.',
            ]);

        $requirementId = collect($studentChecklist->json())->firstWhere('program_requirement_id', $definitionId)['id'];
        $this->post('/api/student/requirements/'.$requirementId.'/upload', [
            'file' => UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        Sanctum::actingAs($coordinator);
        $this->deleteJson('/api/coordinator/requirements/'.$definitionId)
            ->assertOk();

        $this->assertDatabaseHas('program_requirements', [
            'id' => $definitionId,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('internship_requirements', [
            'id' => $requirementId,
            'program_requirement_id' => $definitionId,
        ]);

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/requirements')
            ->assertOk()
            ->assertJsonCount(10)
            ->assertJsonMissing(['requirement_name' => 'Barangay Clearance']);
    }

    public function test_coordinator_cannot_customize_another_programs_requirement(): void
    {
        [$coordinator, $college] = $this->managementContext();
        $otherProgram = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Systems',
            'code' => 'BSIS',
            'is_active' => true,
        ]);
        $definition = ProgramRequirement::create([
            'program_id' => $otherProgram->id,
            'key' => 'custom-other',
            'name' => 'Other Program Document',
            'sort_order' => 1,
        ]);
        Sanctum::actingAs($coordinator);

        $this->putJson('/api/coordinator/requirements/'.$definition->id, [
            'name' => 'Changed Document',
            'is_active' => true,
        ])->assertNotFound();
    }

    public function test_predefined_school_csv_is_previewed_before_ready_students_are_confirmed(): void
    {
        [$coordinator, $college, $program, , $section] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        OjtEnrollment::create([
            'school_id' => '23S0141',
            'full_name' => 'CANTUBA, Rose S',
            'section' => 'BSIT',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $section->id,
            'source' => 'manual',
            'added_by' => $coordinator->id,
        ]);

        $csv = $this->predefinedSchoolCsv([
            ['1', '23S0160', 'BRIONES, Terrence C'],
            ['2.', '23S0141', 'CANTUBA, Rose S'],
            ['3.', '23S0019', 'CONDE, Cassandra Marie R'],
            ['4.', '23S0019', 'DUPLICATE, Student'],
            ['5.', '', 'MISSING, School Number'],
            ['6.', '23S0999', ''],
        ]);

        $preview = $this->post('/api/coordinator/students/import', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            'academic_section_id' => $section->id,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Student list is ready for review.')
            ->assertJsonPath('file_name', 'students.csv')
            ->assertJsonPath('section.id', $section->id)
            ->assertJsonPath('section.name', '4A')
            ->assertJsonPath('summary.students_found', 6)
            ->assertJsonPath('summary.ready', 1)
            ->assertJsonPath('summary.duplicates', 3)
            ->assertJsonPath('summary.invalid', 2)
            ->assertJsonPath('students.0.school_no', '23S0160')
            ->assertJsonPath('students.0.full_name', 'BRIONES, Terrence C')
            ->assertJsonPath('students.0.status', 'Ready')
            ->assertJsonPath('students.1.status', 'Duplicate')
            ->assertJsonPath('students.2.status', 'Duplicate')
            ->assertJsonPath('students.3.status', 'Duplicate')
            ->assertJsonPath('students.4.status', 'Invalid')
            ->assertJsonPath('students.5.status', 'Invalid');

        $this->assertDatabaseMissing('ojt_enrollments', ['school_id' => '23S0160']);
        $this->assertDatabaseMissing('users', ['name' => 'BRIONES, Terrence C']);
        $this->assertDatabaseMissing('students', ['student_id' => '23S0160']);

        $this->post('/api/coordinator/students/import/confirm', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            'academic_section_id' => $section->id,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Student list imported successfully.')
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('skipped', 5);

        $this->assertDatabaseHas('ojt_enrollments', [
            'school_id' => '23S0160',
            'full_name' => 'BRIONES, Terrence C',
            'section' => '4A',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $section->id,
            'source' => 'csv',
        ]);
        $this->assertDatabaseMissing('ojt_enrollments', ['school_id' => '23S0019']);
        $this->assertDatabaseMissing('users', ['name' => 'BRIONES, Terrence C']);
        $this->assertDatabaseMissing('students', ['student_id' => '23S0160']);

        $preview->assertJsonMissingPath('credentials');
    }

    public function test_csv_import_only_accepts_active_sections_from_the_coordinators_program(): void
    {
        [$coordinator, $college] = $this->managementContext();
        $otherProgram = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Systems',
            'code' => 'BSIS',
            'is_active' => true,
        ]);
        $otherSection = AcademicSection::create([
            'program_id' => $otherProgram->id,
            'name' => '4B',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        Sanctum::actingAs($coordinator);

        $this->post('/api/coordinator/students/import', [
            'file' => UploadedFile::fake()->createWithContent(
                'students.csv',
                $this->predefinedSchoolCsv([['1.', '23S1000', 'OUTSIDE, Program']]),
            ),
            'academic_section_id' => $otherSection->id,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('academic_section_id');

        $this->assertDatabaseMissing('ojt_enrollments', ['school_id' => '23S1000']);
    }

    public function test_predefined_import_rejects_wrong_and_empty_templates_without_saving(): void
    {
        [$coordinator, , , , $section] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        foreach ([
            'wrong.csv' => "school_id,full_name\n23S0160,Student Name",
            'empty.csv' => '',
        ] as $fileName => $contents) {
            $this->post('/api/coordinator/students/import', [
                'file' => UploadedFile::fake()->createWithContent($fileName, $contents),
                'academic_section_id' => $section->id,
            ], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file')
                ->assertJsonPath(
                    'errors.file.0',
                    'The uploaded file does not match the required student list template.',
                );
        }

        $this->post('/api/coordinator/students/import', [
            'file' => UploadedFile::fake()->createWithContent(
                'students.txt',
                $this->predefinedSchoolCsv([['1.', '23S0160', 'BRIONES, Terrence C']]),
            ),
            'academic_section_id' => $section->id,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('ojt_enrollments', 0);
    }

    public function test_uploading_the_same_predefined_csv_twice_marks_every_student_duplicate(): void
    {
        [$coordinator, , , , $section] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $csv = $this->predefinedSchoolCsv([
            ['1.', '23S0160', 'BRIONES, Terrence C'],
            ['2.', '23S0141', 'CANTUBA, Rose S'],
        ]);

        $this->post('/api/coordinator/students/import/confirm', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            'academic_section_id' => $section->id,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 2);

        $this->post('/api/coordinator/students/import', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            'academic_section_id' => $section->id,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('summary.ready', 0)
            ->assertJsonPath('summary.duplicates', 2);

        $this->post('/api/coordinator/students/import/confirm', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            'academic_section_id' => $section->id,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('skipped', 2);

        $this->assertDatabaseCount('ojt_enrollments', 2);
    }

    private function managementContext(): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $section = AcademicSection::create([
            'program_id' => $program->id,
            'name' => '4A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $coordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        $hte = HTE::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => 'Partner HTE',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_phone' => '09171111111',
            'is_active' => true,
        ]);

        return [$coordinator, $college, $program, $hte, $section];
    }

    private function predefinedSchoolCsv(array $students): string
    {
        $lines = [
            ',,,,,,,,OFFICIAL LIST OF ENROLLED STUDENTS,,,,,,,,',
            ',,,,,,,,2025-2026 2nd Semester,,,,,,,,',
            ',,,Subject Code,,,ISP307,,,,,,,,Class Section,,,BSIS 3-A',
            ',,#,,Student No,Full Name,,,,,,,Program,,Level',
        ];

        foreach ($students as [$number, $schoolNo, $fullName]) {
            $escapedName = '"'.str_replace('"', '""', $fullName).'"';
            $lines[] = ",,{$number},,{$schoolNo},{$escapedName},,,,,,BSIS";
        }

        $lines[] = 'TOTAL NUMBER OF STUDENTS:  '.count($students);

        return implode("\n", $lines);
    }

    private function studentPayload(College $college, Program $program, HTE $hte): array
    {
        return [
            'student_id' => '2026-1001',
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Intern',
            'email' => 'intern@example.com',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'phone' => '09172222222',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_relationship' => 'Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_phone' => '09173333333',
            'hte_id' => $hte->id,
            'internship_semester' => 'First Semester',
            'internship_year' => '2026-2027',
            'registration_status' => 'pending',
            'internship_status' => 'pending',
            'consent_status' => 'pending',
            'schedule_status' => 'pending',
            'required_ojt_hours' => 486,
            'allow_past_attendance' => false,
        ];
    }

    private function createStudent(College $college, Program $program, HTE $hte): Student
    {
        $payload = $this->studentPayload($college, $program, $hte);
        $user = User::factory()->create(['role' => 'student', 'email' => $payload['email']]);

        unset($payload['email']);

        return Student::create(['user_id' => $user->id, ...$payload]);
    }
}
