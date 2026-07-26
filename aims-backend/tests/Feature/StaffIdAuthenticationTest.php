<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffIdAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_staff_roles_receive_different_role_specific_ids(): void
    {
        $year = now()->format('Y');
        $expectedPrefixes = [
            'coordinator' => 'CO',
            'program_head' => 'PH',
            'vpaa' => 'VP',
            'supervisor' => 'SV',
        ];

        foreach ($expectedPrefixes as $role => $prefix) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertMatchesRegularExpression(
                '/^'.$prefix.'-'.$year.'-\d{3}$/',
                $user->staff_id,
            );
        }

        $firstSupervisor = User::factory()->create(['role' => 'supervisor']);
        $secondSupervisor = User::factory()->create(['role' => 'supervisor']);

        $this->assertNotSame($firstSupervisor->staff_id, $secondSupervisor->staff_id);
    }

    public function test_staff_can_login_with_staff_id_or_email(): void
    {
        $user = User::factory()->create([
            'email' => 'coordinator@example.com',
            'password' => 'password123',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'login' => strtolower($user->staff_id),
            'password' => 'password123',
            'device_name' => 'Staff ID test',
        ])->assertOk()
            ->assertJsonPath('user.staff_id', $user->staff_id)
            ->assertJsonPath('user.role', 'coordinator')
            ->assertJsonStructure(['token']);

        $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password123',
            'device_name' => 'Email test',
        ])->assertOk()
            ->assertJsonPath('user.staff_id', $user->staff_id);
    }

    public function test_admin_created_staff_account_returns_an_automatic_staff_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users', [
            'name' => 'Internship Supervisor',
            'email' => 'supervisor@example.com',
            'password' => 'password123',
            'role' => 'supervisor',
        ])->assertCreated()
            ->assertJsonPath('user.staff_id', 'SV-'.now()->format('Y').'-001');
    }

    public function test_changing_a_staff_role_issues_the_correct_new_staff_id(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $supervisor->update(['role' => 'vpaa']);

        $this->assertStringStartsWith('VP-'.now()->format('Y').'-', $supervisor->fresh()->staff_id);
    }
}
