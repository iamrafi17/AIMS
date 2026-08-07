<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_history_requires_authentication(): void
    {
        $this->getJson('/api/activity-logs')->assertUnauthorized();
    }

    public function test_every_user_including_admin_can_only_retrieve_their_own_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'student']);

        ActivityLog::create([
            'user_id' => $admin->id,
            'activity_type' => 'Account Management',
            'description' => 'Updated a user account.',
        ]);
        ActivityLog::create([
            'user_id' => $otherUser->id,
            'activity_type' => 'Attendance',
            'description' => 'Timed in.',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/activity-logs?user_id='.$otherUser->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $admin->id)
            ->assertJsonMissing(['user_id' => $otherUser->id]);

        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_my_activity_is_available_to_every_supported_role(): void
    {
        foreach (['student', 'coordinator', 'program_head', 'vpaa', 'supervisor', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => 'Authentication',
                'description' => 'Logged in.',
            ]);

            Sanctum::actingAs($user);

            $this->getJson('/api/activity-logs')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.user_id', $user->id);
        }
    }

    public function test_successful_important_actions_are_recorded_but_failed_actions_are_not(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'role' => 'coordinator',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile/account', [])
            ->assertUnprocessable();

        $this->assertDatabaseCount('activity_logs', 0);

        $this->putJson('/api/profile/account', ['name' => 'Updated Name'])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'activity_type' => 'Profile',
            'description' => 'Updated profile.',
        ]);
    }

    public function test_successful_login_and_logout_are_recorded_for_the_authenticated_user(): void
    {
        $user = User::factory()->create([
            'role' => 'coordinator',
            'password' => 'password',
        ]);

        $login = $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'activity-log-test',
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'activity_type' => 'Authentication',
            'description' => 'Logged in.',
        ]);

        $this->withToken($login->json('token'))
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'activity_type' => 'Authentication',
            'description' => 'Logged out.',
        ]);
    }

    public function test_old_admin_audit_endpoint_is_removed(): void
    {
        $this->assertTrue(Schema::hasTable('activity_logs'));
        $this->assertFalse(Schema::hasTable('audit_logs'));

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/audit')->assertNotFound();
    }
}
