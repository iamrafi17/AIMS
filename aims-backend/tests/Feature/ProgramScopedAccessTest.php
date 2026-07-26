<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\College;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramScopedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_only_sees_and_manages_students_in_the_assigned_program(): void
    {
        [$college, $bsis, $bsit] = $this->academicContext();
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $bsis->id,
        ]);
        $ownStudent = $this->student($college, $bsis, '2026-BSIS-01');
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

        $this->putJson('/api/coordinator/attendance/'.$otherAttendance->id.'/verify', [
            'verified' => true,
        ])->assertNotFound();
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
            'file_type' => 'pdf',
        ]);
        $otherRequirement = InternshipRequirement::create([
            'student_id' => $otherStudent->id,
            'requirement_name' => 'Curriculum Vitae',
            'file_path' => 'requirements/other.pdf',
            'file_type' => 'pdf',
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

    private function student(College $college, Program $program, string $schoolId): Student
    {
        $user = User::factory()->create(['role' => 'student']);

        return Student::create([
            'user_id' => $user->id,
            'student_id' => $schoolId,
            'first_name' => 'Test',
            'last_name' => $program->code,
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'phone' => '09170000000',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_phone' => '09171111111',
            'registration_status' => 'approved',
            'internship_status' => 'active',
        ]);
    }
}
