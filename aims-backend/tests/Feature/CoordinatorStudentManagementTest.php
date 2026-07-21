<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_create_update_review_and_delete_a_student(): void
    {
        [$coordinator, $college, $program, $hte] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $payload = $this->studentPayload($college, $program, $hte);

        $created = $this->postJson('/api/coordinator/students', $payload)
            ->assertCreated()
            ->assertJsonPath('student.student_id', '2026-1001')
            ->assertJsonPath('student.user.email', 'intern@example.com')
            ->assertJsonStructure(['temporary_password'])
            ->json('student');

        $this->assertNotEmpty($this->postJson('/api/coordinator/students', [])->json('errors'));

        $payload['first_name'] = 'Updated';
        $payload['email'] = 'updated.intern@example.com';

        $this->putJson('/api/coordinator/students/'.$created['id'], $payload)
            ->assertOk()
            ->assertJsonPath('student.first_name', 'Updated')
            ->assertJsonPath('student.user.email', 'updated.intern@example.com');

        $this->postJson('/api/coordinator/students/'.$created['id'].'/approve')
            ->assertOk();

        $this->assertDatabaseHas('students', [
            'id' => $created['id'],
            'registration_status' => 'approved',
            'internship_status' => 'active',
            'registration_reviewed_by' => $coordinator->id,
        ]);

        $userId = Student::findOrFail($created['id'])->user_id;
        $this->deleteJson('/api/coordinator/students/'.$created['id'])
            ->assertOk();

        $this->assertDatabaseMissing('students', ['id' => $created['id']]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
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

    public function test_coordinator_can_import_students_from_csv_and_receives_credentials(): void
    {
        [$coordinator] = $this->managementContext();
        Sanctum::actingAs($coordinator);

        $csv = implode("\n", [
            'student_id,first_name,middle_name,last_name,email,password,gender,birth_date,address,phone,college_code,program_code,year_level,section,parent_name,parent_relationship,parent_address,parent_phone,hte_name,internship_semester,internship_year,registration_status,internship_status',
            '2026-2001,CSV,,Student,csv.student@example.com,,female,2002-02-02,Santa Cruz,09170000001,CICS,BSIT,4,A,CSV Parent,Parent,Santa Cruz,09170000002,Partner HTE,First Semester,2026-2027,pending,pending',
            '2026-2002,Bad,,Program,bad.program@example.com,Password123!,male,2002-03-03,Santa Cruz,09170000003,CICS,UNKNOWN,4,A,Bad Parent,Parent,Santa Cruz,09170000004,,First Semester,2026-2027,pending,pending',
        ]);

        $this->post('/api/coordinator/students/import', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('credentials.0.student_id', '2026-2001')
            ->assertJsonPath('credentials.0.email', 'csv.student@example.com')
            ->assertJsonStructure(['credentials' => [['temporary_password']]]);

        $this->assertDatabaseHas('students', ['student_id' => '2026-2001']);
        $this->assertDatabaseMissing('students', ['student_id' => '2026-2002']);
    }

    private function managementContext(): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $hte = HTE::create([
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
