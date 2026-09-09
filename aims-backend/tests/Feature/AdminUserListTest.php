<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_users_by_college_and_choose_a_page_size(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $college = College::create(['name' => 'College of Computing', 'code' => 'CCS']);
        $otherCollege = College::create(['name' => 'College of Education', 'code' => 'CED']);
        $matchingUser = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id]);
        $otherUser = User::factory()->create(['role' => 'coordinator', 'college_id' => $otherCollege->id]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/users?college_id={$college->id}&per_page=10")
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $matchingUser->id)
            ->assertJsonMissing(['id' => $otherUser->id]);
    }

    public function test_admin_user_list_includes_student_college_and_program_assignments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $college = College::create(['name' => 'College of Computing', 'code' => 'CCS']);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'BSIT',
        ]);
        $studentUser = User::factory()->create([
            'role' => 'student',
            'college_id' => null,
            'program_id' => null,
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-0001',
            'first_name' => 'Student',
            'last_name' => 'Account',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Boac, Marinduque',
            'contact_number' => '09170000000',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Boac, Marinduque',
            'parent_contact_number' => '09171111111',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/users?role=student&college_id={$college->id}")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $studentUser->id)
            ->assertJsonPath('data.0.college.code', 'CCS')
            ->assertJsonPath('data.0.program.code', 'BSIT')
            ->assertJsonPath('data.0.student.college.code', 'CCS')
            ->assertJsonPath('data.0.student.program.code', 'BSIT');
    }
}
