<?php

namespace Tests\Feature;

use App\Models\HTE;
use App\Models\HteDepartment;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserIdAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_uses_username_for_staff_login(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'username'));
        $this->assertFalse(Schema::hasColumn('users', 'user_id'));
        $this->assertFalse(Schema::hasColumn('users', 'staff_id'));
    }

    public function test_supported_staff_roles_receive_different_role_specific_ids(): void
    {
        $year = now()->format('Y');
        $expectedPrefixes = [
            'admin' => 'AD',
            'coordinator' => 'CO',
            'program_head' => 'PH',
            'vpaa' => 'VP',
            'supervisor' => 'SV',
        ];

        foreach ($expectedPrefixes as $role => $prefix) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertMatchesRegularExpression(
                '/^'.$prefix.'-'.$year.'-\d{3}$/',
                $user->username,
            );
        }

        $firstSupervisor = User::factory()->create(['role' => 'supervisor']);
        $secondSupervisor = User::factory()->create(['role' => 'supervisor']);

        $this->assertNotSame($firstSupervisor->username, $secondSupervisor->username);
    }

    public function test_staff_can_login_with_username_or_email(): void
    {
        $user = User::factory()->create([
            'email' => 'coordinator@example.com',
            'password' => 'password123',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'login' => strtolower($user->username),
            'password' => 'password123',
            'device_name' => 'User ID test',
        ])->assertOk()
            ->assertJsonPath('user.username', $user->username)
            ->assertJsonPath('user.role', 'coordinator')
            ->assertJsonStructure(['token']);

        $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password123',
            'device_name' => 'Email test',
        ])->assertOk()
            ->assertJsonPath('user.username', $user->username);
    }

    public function test_student_school_id_is_saved_as_username_and_can_be_used_to_login(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'password' => 'password123',
        ]);
        Student::create([
            'user_id' => $user->id,
            'student_id' => '26S0123',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'address' => 'Boac, Marinduque',
            'contact_number' => '09170000000',
        ]);

        $this->assertSame('26S0123', $user->fresh()->username);

        $this->postJson('/api/login', [
            'login' => '26S0123',
            'password' => 'password123',
            'device_name' => 'Student username test',
        ])->assertOk()->assertJsonPath('user.username', '26S0123');
    }

    public function test_admin_created_staff_account_returns_an_automatic_username(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hte = HTE::create([
            'name' => 'Partner HTE',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Contact Person',
            'contact_email' => 'partner@example.com',
            'contact_number' => '09170000000',
            'geofence_radius' => 100,
            'is_active' => true,
        ]);
        $department = HteDepartment::create([
            'hte_id' => $hte->id,
            'department_name' => 'Operations Office',
            'normalized_name' => 'operations office',
        ]);
        $supervisor = Supervisor::create([
            'hte_department_id' => $department->id,
            'full_name' => 'Internship Supervisor',
            'normalized_name' => 'internship supervisor',
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users', [
            'email' => 'internship.supervisor@example.com',
            'role' => 'supervisor',
            'supervisor_record_id' => $supervisor->id,
        ])->assertCreated()
            ->assertJsonPath('user.username', 'SV-'.now()->format('Y').'-001')
            ->assertJsonPath('user.requires_security_setup', true);
    }

    public function test_changing_a_staff_role_issues_the_correct_new_username(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $supervisor->update(['role' => 'vpaa']);

        $this->assertStringStartsWith('VP-'.now()->format('Y').'-', $supervisor->fresh()->username);
    }
}
