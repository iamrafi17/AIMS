<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_by_identifier_and_ip(): void
    {
        User::factory()->create([
            'email' => 'rate-limit@example.com',
            'password' => 'correct-password',
            'role' => 'coordinator',
        ]);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/login', [
                'login' => 'rate-limit@example.com',
                'password' => 'wrong-password',
                'device_name' => 'Security test',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'login' => 'rate-limit@example.com',
            'password' => 'wrong-password',
            'device_name' => 'Security test',
        ])->assertTooManyRequests();
    }

    public function test_inactive_user_cannot_reuse_an_existing_bearer_token(): void
    {
        $user = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        $plainToken = $user->createToken('Existing browser')->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($plainToken)
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account has been deactivated.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_announcement_details_reads_and_files_are_limited_to_the_target_audience(): void
    {
        Storage::fake('local');
        $author = User::factory()->create(['role' => 'vpaa']);
        $student = User::factory()->create(['role' => 'student']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        Storage::disk('local')->put('announcements/private.pdf', 'private document');
        $announcement = Announcement::create([
            'title' => 'Coordinator advisory',
            'content' => 'Restricted announcement.',
            'category' => 'advisory',
            'author_id' => $author->id,
            'target_audience' => 'coordinators',
            'attachment_path' => 'announcements/private.pdf',
            'attachment_name' => 'advisory.pdf',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($student);
        $this->getJson("/api/announcements/{$announcement->id}")->assertNotFound();
        $this->postJson("/api/announcements/{$announcement->id}/read")->assertNotFound();
        $this->get("/api/announcements/{$announcement->id}/download")->assertNotFound();
        $this->assertDatabaseCount('announcement_reads', 0);

        Sanctum::actingAs($coordinator);
        $this->getJson("/api/announcements/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('id', $announcement->id);
        $this->postJson("/api/announcements/{$announcement->id}/read")->assertOk();
        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $coordinator->id,
        ]);
    }

    public function test_admin_password_reset_revokes_existing_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'supervisor']);
        $target->createToken('Compromised browser');
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$target->id}/reset-password", [
            'password' => 'new-secure-password',
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $target->id,
        ]);
    }

    public function test_admin_cannot_change_their_own_role_through_the_general_update_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'student',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot change the role of your current account.');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_bearer_tokens_have_a_bounded_lifetime(): void
    {
        $this->assertSame(480, config('sanctum.expiration'));
        $this->assertSame('aims_', config('sanctum.token_prefix'));
    }

    public function test_api_responses_include_security_headers(): void
    {
        $this->getJson('/api/portal')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');
    }
}
