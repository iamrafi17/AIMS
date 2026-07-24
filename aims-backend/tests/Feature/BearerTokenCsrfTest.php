<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BearerTokenCsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_vite_origin_can_login_and_use_bearer_token_without_a_csrf_cookie(): void
    {
        User::factory()->create([
            'email' => 'coordinator@example.com',
            'password' => 'password123',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $login = $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', [
                'login' => 'coordinator@example.com',
                'password' => 'password123',
                'device_name' => 'AIMS test browser',
            ])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Authorization' => 'Bearer '.$login->json('token'),
        ])->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');
    }
}
