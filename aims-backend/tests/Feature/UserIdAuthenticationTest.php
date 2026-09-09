<?php

namespace Tests\Feature;

use App\Models\HTE;
use App\Models\HteDepartment;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserIdAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_uses_user_id_instead_of_staff_id(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'user_id'));
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
                $user->user_id,
            );
        }

        $firstSupervisor = User::factory()->create(['role' => 'supervisor']);
        $secondSupervisor = User::factory()->create(['role' => 'supervisor']);

        $this->assertNotSame($firstSupervisor->user_id, $secondSupervisor->user_id);
    }

    public function test_staff_can_login_with_user_id_or_email(): void
    {
        $user = User::factory()->create([
            'email' => 'coordinator@example.com',
            'password' => 'password123',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'login' => strtolower($user->user_id),
            'password' => 'password123',
            'device_name' => 'User ID test',
        ])->assertOk()
            ->assertJsonPath('user.user_id', $user->user_id)
            ->assertJsonPath('user.role', 'coordinator')
            ->assertJsonStructure(['token']);

        $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password123',
            'device_name' => 'Email test',
        ])->assertOk()
            ->assertJsonPath('user.user_id', $user->user_id);
    }

    public function test_admin_created_staff_account_returns_an_automatic_user_id(): void
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
            'role' => 'supervisor',
            'supervisor_record_id' => $supervisor->id,
        ])->assertCreated()
            ->assertJsonPath('user.user_id', 'SV-'.now()->format('Y').'-001')
            ->assertJsonPath('user.requires_security_setup', true);
    }

    public function test_changing_a_staff_role_issues_the_correct_new_user_id(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $supervisor->update(['role' => 'vpaa']);

        $this->assertStringStartsWith('VP-'.now()->format('Y').'-', $supervisor->fresh()->user_id);
    }
}
