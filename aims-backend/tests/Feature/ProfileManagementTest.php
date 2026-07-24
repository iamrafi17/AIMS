<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_personal_profile_information(): void
    {
        [$user, $student] = $this->studentAccount();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile/information', [
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Reyes',
            'gender' => 'female',
            'birth_date' => '2003-05-14',
        ])->assertOk()
            ->assertJsonPath('message', 'Profile information updated successfully.')
            ->assertJsonPath('student.first_name', 'Maria');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Maria Santos Reyes',
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Reyes',
            'gender' => 'female',
        ]);
    }

    public function test_student_can_manage_contact_information(): void
    {
        [$user, $student] = $this->studentAccount();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile/contact', [
            'email' => 'maria.reyes@example.com',
            'phone' => '09171234567',
            'address' => 'Santa Cruz, Marinduque',
        ])->assertOk()
            ->assertJsonPath('message', 'Contact information updated successfully.')
            ->assertJsonPath('email', 'maria.reyes@example.com')
            ->assertJsonPath('phone', '09171234567')
            ->assertJsonPath('address', 'Santa Cruz, Marinduque');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'maria.reyes@example.com',
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'phone' => '09171234567',
            'address' => 'Santa Cruz, Marinduque',
        ]);
    }

    public function test_student_must_supply_the_current_password_before_changing_it(): void
    {
        [$user] = $this->studentAccount();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        $this->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_coordinator_can_update_account_and_contact_information(): void
    {
        $coordinator = User::factory()->create([
            'name' => 'Original Coordinator',
            'role' => 'coordinator',
        ]);
        Sanctum::actingAs($coordinator);

        $this->putJson('/api/profile/account', [
            'name' => 'Ralph Joseph Grimaldo',
        ])->assertOk()
            ->assertJsonPath('message', 'Account information updated successfully.')
            ->assertJsonPath('user.name', 'Ralph Joseph Grimaldo');

        $this->putJson('/api/profile/contact', [
            'email' => 'coordinator@marsu.edu.ph',
            'phone' => '09181234567',
            'address' => 'MarSU Santa Cruz Campus',
        ])->assertOk()
            ->assertJsonPath('email', 'coordinator@marsu.edu.ph')
            ->assertJsonPath('phone', '09181234567')
            ->assertJsonPath('address', 'MarSU Santa Cruz Campus');

        $this->assertDatabaseHas('users', [
            'id' => $coordinator->id,
            'name' => 'Ralph Joseph Grimaldo',
            'email' => 'coordinator@marsu.edu.ph',
            'phone' => '09181234567',
            'address' => 'MarSU Santa Cruz Campus',
        ]);
    }

    private function studentAccount(): array
    {
        $user = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'role' => 'student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'student_id' => '22-00001',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'gender' => 'male',
            'birth_date' => '2002-01-15',
            'address' => 'Boac, Marinduque',
            'phone' => '09170000000',
            'section' => 'BSIT 4A',
        ]);

        return [$user, $student];
    }
}
