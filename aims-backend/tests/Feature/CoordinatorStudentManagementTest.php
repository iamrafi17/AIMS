<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
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

    public function test_coordinator_can_update_review_and_delete_a_registered_student(): void
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

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_coordinator_can_manually_add_an_ojt_enrollment_without_credentials(): void
    {
        [$coordinator] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $this->postJson('/api/coordinator/students', [
            'full_name' => 'Juan Dela Cruz',
            'school_id' => '2026-QUICK-01',
            'section' => 'BSIT 4A',
        ])->assertCreated()
            ->assertJsonPath('enrollment.school_id', '2026-QUICK-01')
            ->assertJsonPath('enrollment.full_name', 'Juan Dela Cruz')
            ->assertJsonPath('enrollment.section', 'BSIT 4A')
            ->assertJsonMissingPath('temporary_password');

        $this->assertDatabaseHas('ojt_enrollments', [
            'school_id' => '2026-QUICK-01',
            'full_name' => 'Juan Dela Cruz',
            'section' => 'BSIT 4A',
            'source' => 'manual',
        ]);
        $this->assertDatabaseMissing('students', ['student_id' => '2026-QUICK-01']);
        $this->assertDatabaseMissing('users', ['name' => 'Juan Dela Cruz']);
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

    public function test_coordinator_can_import_student_enrollments_without_creating_credentials(): void
    {
        [$coordinator] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $csv = implode("\n", [
            'school_id,full_name,section',
            '2026-2001,Maria Santos,BSIT 4A',
            '2026-2002,Pedro Reyes,BSIT 4B',
            '2026-2001,Duplicate Student,BSIT 4C',
        ]);

        $this->post('/api/coordinator/students/import', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('failed', 1)
            ->assertJsonMissingPath('credentials');

        $this->assertDatabaseHas('ojt_enrollments', ['school_id' => '2026-2001', 'source' => 'csv']);
        $this->assertDatabaseHas('ojt_enrollments', ['school_id' => '2026-2002', 'source' => 'csv']);
        $this->assertDatabaseMissing('students', ['student_id' => '2026-2001']);
    }

    private function managementContext(): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
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

        return [$coordinator, $college, $program, $hte];
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
