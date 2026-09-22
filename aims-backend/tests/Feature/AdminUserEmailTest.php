<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_staff_email_is_saved_searchable_and_usable_for_first_login(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $created = $this->postJson('/api/admin/users', [
            'name' => 'New VPAA',
            'email' => 'vpaa@example.com',
            'role' => 'vpaa',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'vpaa@example.com')
            ->assertJsonPath('user.requires_profile_setup', true);

        $user = User::findOrFail($created->json('user.id'));
        $this->assertSame('vpaa@example.com', $user->email);
        $this->assertTrue(Hash::check($created->json('temporary_password'), $user->password));

        $this->getJson('/api/admin/users?search=vpaa@example.com')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.email', 'vpaa@example.com');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', [
            'login' => 'vpaa@example.com',
            'password' => $created->json('temporary_password'),
            'device_name' => 'New staff email login',
        ])->assertOk()
            ->assertJsonPath('user.user_id', $user->user_id)
            ->assertJsonPath('user.email', 'vpaa@example.com')
            ->assertJsonPath('user.onboarding_step', 'complete_profile');
    }

    public function test_creation_rejects_missing_invalid_duplicate_and_oversized_email_without_creating_accounts(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email' => 'existing@example.com',
        ]));

        foreach ([null, '', 'invalid-email', 'existing@example.com', str_repeat('a', 245).'@example.com'] as $email) {
            $payload = ['name' => 'New VPAA', 'role' => 'vpaa'];
            if ($email !== null) {
                $payload['email'] = $email;
            }

            $this->postJson('/api/admin/users', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email');
        }

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_keep_or_change_staff_email_without_changing_user_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $staff = User::factory()->create(['role' => 'vpaa', 'email' => 'old@example.com']);

        foreach (['old@example.com', 'new@example.com'] as $email) {
            $this->putJson('/api/admin/users/'.$staff->id, [
                'name' => $staff->name,
                'email' => $email,
            ])->assertOk()
                ->assertJsonPath('user.email', $email)
                ->assertJsonPath('user.user_id', $staff->user_id);
            $this->assertSame($email, $staff->fresh()->email);
        }

        $this->putJson('/api/admin/users/'.$staff->id, ['name' => 'Updated VPAA'])
            ->assertOk()
            ->assertJsonPath('user.email', 'new@example.com');
    }

    public function test_update_rejects_empty_invalid_duplicate_and_oversized_email_and_preserves_saved_email(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email' => 'existing@example.com',
        ]));
        $staff = User::factory()->create(['role' => 'vpaa', 'email' => 'staff@example.com']);

        foreach ([null, '', 'invalid-email', 'existing@example.com', str_repeat('a', 245).'@example.com'] as $email) {
            $this->putJson('/api/admin/users/'.$staff->id, [
                'name' => $staff->name,
                'email' => $email,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('email');
            $this->assertSame('staff@example.com', $staff->fresh()->email);
        }
    }
}
