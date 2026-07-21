<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_and_remove_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent(
                'profile.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
            ),
        ])->assertOk()
            ->assertJsonPath('message', 'Profile photo updated successfully.');

        $path = $response->json('avatar');
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($user->fresh()->avatar);

        $this->deleteJson('/api/profile/avatar')
            ->assertOk()
            ->assertJsonPath('avatar', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->fresh()->avatar);
    }
}
