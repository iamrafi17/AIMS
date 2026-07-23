<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_creates_their_own_credentials_during_registration(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        OjtEnrollment::create([
            'school_id' => '2026-REG-001',
            'full_name' => 'Maria Dela Santos',
            'section' => 'BSIT 4A',
            'source' => 'csv',
            'added_by' => $coordinator->id,
        ]);

        $this->getJson('/api/registration/enrollment/2026-REG-001')
            ->assertOk()
            ->assertJsonPath('full_name', 'Maria Dela Santos')
            ->assertJsonPath('section', 'BSIT 4A');

        $payload = [
            'student_id' => '2026-REG-001',
            'first_name' => 'Tampered',
            'last_name' => 'Name',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'phone' => '09171234567',
            'email' => 'maria.santos@student.marsu.edu.ph',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'Wrong Section',
            'parent_name' => 'Parent Santos',
            'parent_relationship' => 'Mother',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_phone' => '09179876543',
            'hte_id' => null,
            'internship_semester' => 'First Semester',
            'internship_year' => '2026-2027',
            'agree_terms' => true,
            'agree_privacy' => true,
            'password' => 'StudentPass123!',
            'password_confirmation' => 'StudentPass123!',
        ];

        $response = $this->postJson('/api/register', $payload)
            ->assertCreated()
            ->assertJsonPath('user.email', 'maria.santos@student.marsu.edu.ph')
            ->assertJsonPath('student.student_id', '2026-REG-001');

        $this->assertDatabaseHas('students', [
            'id' => $response->json('student.id'),
            'student_id' => '2026-REG-001',
            'first_name' => 'Maria Dela',
            'last_name' => 'Santos',
            'section' => 'BSIT 4A',
        ]);
        $this->assertDatabaseHas('ojt_enrollments', [
            'school_id' => '2026-REG-001',
            'student_record_id' => $response->json('student.id'),
        ]);

        $payload['email'] = 'another@student.marsu.edu.ph';
        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    public function test_unlisted_school_id_cannot_register(): void
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);

        $this->postJson('/api/register', [
            'student_id' => 'NOT-LISTED',
            'first_name' => 'Not',
            'last_name' => 'Listed',
            'gender' => 'male',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz',
            'phone' => '09170000000',
            'email' => 'not.listed@example.com',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Parent',
            'parent_relationship' => 'Father',
            'parent_address' => 'Santa Cruz',
            'parent_phone' => '09171111111',
            'hte_id' => null,
            'internship_semester' => 'First Semester',
            'internship_year' => '2026-2027',
            'agree_terms' => true,
            'agree_privacy' => true,
            'password' => 'StudentPass123!',
            'password_confirmation' => 'StudentPass123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }
}
