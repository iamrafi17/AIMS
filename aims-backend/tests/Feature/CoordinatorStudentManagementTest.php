<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\ApprovalRecord;
use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\ProgramRequirement;
use App\Models\Student;
use App\Models\StudentDocumentApproval;
use App\Models\StudentInternship;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_update_and_delete_without_registration_review(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $payload = $this->studentPayload($college, $program, $hte);
        $student = $this->createStudent($college, $program, $hte);
        $originalPassword = $student->user->password;
        $student->update(['registration_status' => 'approved', 'internship_status' => 'pending']);

        $payload['first_name'] = 'Updated';
        $payload['middle_name'] = 'Middle';
        $payload['email'] = 'updated.intern@example.com';
        $payload['contact_number'] = '09179999999';
        $payload['address'] = 'Updated Address';
        $payload['gender'] = 'male';
        $payload['birth_date'] = '2003-03-03';
        $payload['registration_status'] = 'rejected';
        $payload['password'] = 'AutofilledPassword123!';

        $this->putJson('/api/coordinator/students/'.$student->id, $payload)
            ->assertOk()
            ->assertJsonPath('student.first_name', 'Updated')
            ->assertJsonPath('student.user.email', 'updated.intern@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $student->user_id,
            'email' => 'updated.intern@example.com',
        ]);
        $this->assertSame($originalPassword, $student->user->fresh()->password);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Updated',
            'middle_name' => 'Middle',
            'last_name' => 'Intern',
            'gender' => 'female',
            'birth_date' => '2002-01-01 00:00:00',
            'contact_number' => '09172222222',
            'address' => 'Santa Cruz, Marinduque',
        ]);

        $this->postJson('/api/coordinator/students/'.$student->id.'/approve')
            ->assertNotFound();
        $this->postJson('/api/coordinator/students/'.$student->id.'/reject', ['reason' => 'Former workflow'])
            ->assertNotFound();

        $this->assertDatabaseHas('ojt_enrollments', [
            'student_record_id' => $student->id,
            'registration_status' => 'approved',
        ]);
        $this->assertDatabaseHas('student_internships', [
            'student_id' => $student->id,
            'status' => 'pending',
            'is_current' => true,
        ]);

        $userId = $student->user_id;
        $this->deleteJson('/api/coordinator/students/'.$student->id)
            ->assertOk()
            ->assertJsonPath('message', 'Student record deleted successfully.');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_coordinator_can_manually_add_an_ojt_enrollment_without_credentials(): void
    {
        [$coordinator, , , , $section] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $this->postJson('/api/coordinator/students', [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'school_id' => '2026-QUICK-01',
            'academic_section_id' => $section->id,
        ])->assertCreated()
            ->assertJsonPath('enrollment.school_id', '2026-QUICK-01')
            ->assertJsonPath('enrollment.first_name', 'Juan')
            ->assertJsonPath('enrollment.middle_name', 'Santos')
            ->assertJsonPath('enrollment.last_name', 'Dela Cruz')
            ->assertJsonPath('enrollment.section', '4A')
            ->assertJsonMissingPath('temporary_password');

        $this->assertDatabaseHas('ojt_enrollments', [
            'school_id' => '2026-QUICK-01',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'academic_section_id' => $section->id,
            'source' => 'manual',
        ]);
        $this->assertDatabaseMissing('students', ['student_id' => '2026-QUICK-01']);
        $this->assertDatabaseMissing('users', ['name' => 'Juan Dela Cruz']);
    }

    public function test_hte_department_changes_must_use_student_deployments(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $department = $hte->departments()->create([
            'department_name' => 'Research Office',
            'normalized_name' => 'research office',
        ]);
        $department->programs()->attach($program->id, [
            'linked_by' => $coordinator->id,
            'is_active' => true,
        ]);
        $student = $this->createStudent($college, $program, $hte);

        $this->getJson('/api/coordinator/students/options')
            ->assertOk()
            ->assertJsonPath('htes.0.departments.0.department_name', 'Research Office');

        $payload = $this->studentPayload($college, $program, $hte);
        $payload['hte_department_id'] = $department->id;
        $this->putJson('/api/coordinator/students/'.$student->id, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hte_id');
        $this->assertNull($student->fresh()->hte_department_id);

        $payload['hte_department_id'] = null;
        $payload['internship_status'] = 'active';
        $this->putJson('/api/coordinator/students/'.$student->id, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('internship_status');

        $payload['internship_status'] = 'pending';
        $payload['schedule_status'] = 'approved';
        $this->putJson('/api/coordinator/students/'.$student->id, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_status');

        $payload['schedule_status'] = 'pending';
        $payload['ojt_start_date'] = now()->addWeek()->toDateString();
        $this->putJson('/api/coordinator/students/'.$student->id, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ojt_start_date');
    }

    public function test_coordinator_can_choose_the_ojt_enrollment_page_size(): void
    {
        [$coordinator, $college, $program, , $section] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        foreach (range(1, 11) as $number) {
            OjtEnrollment::create([
                'school_id' => '2026-PAGE-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'first_name' => 'Enrollment',
                'last_name' => 'Student '.$number,
                'academic_section_id' => $section->id,
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
        [$coordinator, $college, $program, , $section] = $this->managementContext();
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
                'contact_number' => '09170000000',
                'college_id' => $college->id,
                'program_id' => $program->id,
                'academic_section_id' => $section->id,
                'year_level' => 4,
                'section' => 'A',
                'parent_name' => 'Test Parent',
                'parent_address' => 'Boac, Marinduque',
                'parent_contact_number' => '09171111111',
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

        $this->postJson("/api/coordinator/students/{$student->id}/requirements/{$requirement->id}/review", [
            'decision' => 'rejected',
        ])->assertOk()->assertJsonPath('requirement.feedback', null);
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
            'file' => UploadedFile::fake()->create('clearance.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->post('/api/student/requirements/'.$requirementId.'/upload', [
            'file' => UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('requirement.file_type', 'pdf');

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

    public function test_student_can_remove_own_submitted_file_and_reset_review(): void
    {
        /** @var FilesystemAdapter $privateDisk */
        $privateDisk = Storage::fake('local');
        [, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        InternshipRequirement::ensureForStudent($student->id);
        $requirement = InternshipRequirement::where('student_id', $student->id)->firstOrFail();
        Sanctum::actingAs($student->user);

        $this->post('/api/student/requirements/'.$requirement->id.'/upload', [
            'file' => UploadedFile::fake()->create('consent.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();
        $path = $requirement->fresh()->file_path;
        $privateDisk->assertExists($path);
        $requirement->update(['status' => 'rejected', 'feedback' => 'Try again', 'reviewed_at' => now()]);

        $this->deleteJson('/api/student/requirements/'.$requirement->id.'/file')
            ->assertOk()
            ->assertJsonPath('requirement.file_path', null)
            ->assertJsonPath('requirement.status', 'pending');

        $this->assertDatabaseHas('internship_requirements', [
            'id' => $requirement->id,
            'file_path' => null,
            'status' => 'pending',
            'feedback' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
        $privateDisk->assertMissing($path);
        $this->deleteJson('/api/student/requirements/'.$requirement->id.'/file')->assertNotFound();
    }

    public function test_student_cannot_remove_another_students_file_or_one_under_approval(): void
    {
        /** @var FilesystemAdapter $privateDisk */
        $privateDisk = Storage::fake('local');
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        InternshipRequirement::ensureForStudent($student->id);
        $requirement = InternshipRequirement::where('student_id', $student->id)->firstOrFail();
        $path = UploadedFile::fake()->create('consent.pdf', 100, 'application/pdf')
            ->store('requirements/'.$student->id, 'local');
        $requirement->update(['file_path' => $path]);

        $otherStudent = User::factory()->create(['role' => 'student']);
        Student::create([
            'user_id' => $otherStudent->id,
            'student_id' => '2026-OTHER',
            'first_name' => 'Other',
            'last_name' => 'Student',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ]);
        Sanctum::actingAs($otherStudent);
        $this->deleteJson('/api/student/requirements/'.$requirement->id.'/file')->assertNotFound();

        ApprovalRecord::submit(ApprovalRecord::TYPE_DOCUMENT, $requirement->id, $coordinator->id);
        Sanctum::actingAs($student->user);
        $this->deleteJson('/api/student/requirements/'.$requirement->id.'/file')->assertUnprocessable();
        $privateDisk->assertExists($path);
        $this->assertDatabaseHas('internship_requirements', ['id' => $requirement->id, 'file_path' => $path]);
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
            'first_name' => 'Rose',
            'middle_name' => 'S',
            'last_name' => 'CANTUBA',
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
            ->assertJsonPath('students.0.first_name', 'Terrence')
            ->assertJsonPath('students.0.middle_name', 'C')
            ->assertJsonPath('students.0.last_name', 'BRIONES')
            ->assertJsonPath('students.2.first_name', 'Cassandra Marie')
            ->assertJsonPath('students.2.middle_name', 'R')
            ->assertJsonPath('students.2.last_name', 'CONDE')
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
            'first_name' => 'Terrence',
            'middle_name' => 'C',
            'last_name' => 'BRIONES',
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
            'academic_period_id' => $this->academicPeriod()->id,
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

    public function test_coordinator_cannot_review_a_locked_student_folder(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        InternshipRequirement::ensureForStudent($student->id);
        $requirement = $student->requirements()->firstOrFail();
        $requirement->update(['file_path' => 'review.pdf', 'status' => 'approved']);
        $approval = StudentDocumentApproval::create([
            'student_internship_id' => $requirement->student_internship_id,
            'endorsed_by' => $coordinator->id,
            'endorsed_at' => now(),
            'status' => StudentDocumentApproval::FOR_REVIEW,
        ]);
        Sanctum::actingAs($coordinator);

        foreach ([StudentDocumentApproval::FOR_REVIEW, StudentDocumentApproval::FINAL_APPROVED] as $status) {
            $approval->update(['status' => $status]);
            $this->postJson("/api/coordinator/students/{$student->id}/requirements/{$requirement->id}/review", [
                'decision' => 'rejected', 'feedback' => 'Requires revision',
            ])->assertUnprocessable();
            $this->assertSame('approved', $requirement->fresh()->status);
            $this->assertSame($status, $approval->fresh()->status);
        }

        $approval->update(['status' => StudentDocumentApproval::RETURNED]);
        $this->postJson("/api/coordinator/students/{$student->id}/requirements/{$requirement->id}/review", [
            'decision' => 'rejected', 'feedback' => 'Requires revision',
        ])->assertOk();
        $this->assertSame('rejected', $requirement->fresh()->status);
    }

    public function test_new_internship_has_its_own_checklist_and_cannot_edit_historical_uploads(): void
    {
        [, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        InternshipRequirement::ensureForStudent($student->id);
        $old = $student->currentInternship;
        $oldCount = $old->requirements()->count();
        $oldRequirement = $old->requirements()->firstOrFail();
        $oldRequirement->update(['file_path' => 'historical.pdf', 'status' => 'approved']);
        $section = AcademicSection::create([
            'academic_period_id' => $old->academicSection->academic_period_id,
            'program_id' => $program->id, 'name' => '4B', 'year_level' => 4, 'is_active' => true,
        ]);
        $old->update(['is_current' => false]);
        $new = StudentInternship::create([
            'student_id' => $student->id, 'academic_section_id' => $section->id,
            'status' => 'pending', 'is_current' => true,
        ]);
        InternshipRequirement::ensureForStudent($student->id);
        InternshipRequirement::ensureForStudent($student->id);
        $this->assertGreaterThan(0, $oldCount);
        $this->assertSame($oldCount, $new->requirements()->count());
        $this->assertSame($oldCount, $old->requirements()->count());
        $this->assertSame('historical.pdf', $oldRequirement->fresh()->file_path);
        $this->assertSame('approved', $oldRequirement->fresh()->status);
        $this->assertSame(0, $new->requirements()->whereNotNull('file_path')->count());

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/requirements')->assertOk()->assertJsonCount($oldCount)
            ->assertJsonPath('0.student_internship_id', $new->id);
        $this->deleteJson("/api/student/requirements/{$oldRequirement->id}/file")->assertNotFound();
        $this->getJson("/api/student/requirements/{$oldRequirement->id}/download")->assertNotFound();
        $this->post("/api/student/requirements/{$oldRequirement->id}/upload", [
            'file' => UploadedFile::fake()->create('replacement.pdf', 1, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertNotFound();
        $this->assertSame('historical.pdf', $oldRequirement->fresh()->file_path);
    }

    public function test_checklist_sync_skips_students_without_a_current_internship(): void
    {
        [, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        $student->currentInternship->update(['is_current' => false]);
        InternshipRequirement::ensureForStudent($student->id);
        $this->assertSame(0, $student->requirements()->count());
    }

    public function test_deleting_student_with_empty_checklist_cleans_up_restricting_rows(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        InternshipRequirement::ensureForStudent($student->id);
        $this->assertGreaterThan(0, $student->requirements()->count());
        Sanctum::actingAs($coordinator);
        $this->deleteJson("/api/coordinator/students/{$student->id}")->assertOk();
        $this->assertDatabaseMissing('internship_requirements', ['student_id' => $student->id]);
        $this->assertDatabaseMissing('student_internships', ['student_id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $student->user_id]);
    }

    public function test_student_deletion_preserves_attendance_and_submitted_documents(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        $student = $this->createStudent($college, $program, $hte);
        InternshipRequirement::ensureForStudent($student->id);
        $requirement = $student->requirements()->firstOrFail();
        $requirement->update(['file_path' => 'submitted.pdf']);
        Sanctum::actingAs($coordinator);
        $this->deleteJson("/api/coordinator/students/{$student->id}")->assertUnprocessable();
        $this->assertSame('submitted.pdf', $requirement->fresh()->file_path);

        $requirement->update(['file_path' => null]);
        $attendance = Attendance::create([
            'student_id' => $student->id, 'date' => '2026-10-08', 'status' => 'absent',
            'work_mode' => 'wfo', 'session_type' => 'full_day',
        ]);
        $this->deleteJson("/api/coordinator/students/{$student->id}")->assertUnprocessable();
        $this->assertDatabaseHas('attendance', ['id' => $attendance->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('users', ['id' => $student->user_id]);
    }

    private function managementContext(): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $period = $this->academicPeriod();
        $section = AcademicSection::create([
            'program_id' => $program->id,
            'academic_period_id' => $period->id,
            'name' => '4A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $coordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $section->id,
            'coordinator_id' => $coordinator->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $hte = HTE::create([
            'name' => 'Partner HTE',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_number' => '09171111111',
            'is_active' => true,
        ]);
        $hte->programs()->attach($program->id, [
            'linked_by' => $coordinator->id,
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
            'contact_number' => '09172222222',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_relationship' => 'Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09173333333',
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

        return Student::create([
            'user_id' => $user->id,
            ...$payload,
            'academic_section_id' => AcademicSection::where('program_id', $program->id)->firstOrFail()->id,
        ]);
    }
}
