<?php

namespace Tests\Feature;

use App\Models\SecurityQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffPasswordLengthTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('passwordLengths')]
    public function test_staff_password_setup_accepts_only_eight_to_twelve_characters(string $endpoint, string $password, bool $valid): void
    {
        $user = User::factory()->create([
            'role' => 'vpaa', 'password' => 'TempPass123!',
            'requires_password_change' => true,
            'requires_security_setup' => $endpoint === 'security-setup',
        ]);
        $token = $this->postJson('/api/login', [
            'login' => $user->user_id, 'password' => 'TempPass123!', 'device_name' => 'Password length test',
        ])->assertOk()->json('token');
        $payload = ['password' => $password, 'password_confirmation' => $password];
        if ($endpoint === 'security-setup') {
            $payload['security_questions'] = SecurityQuestion::query()->take(3)->get()->values()
                ->map(fn (SecurityQuestion $question, int $index) => [
                    'security_question_id' => $question->id, 'answer' => 'Private answer '.($index + 1),
                ])->all();
        }

        $response = $this->withToken($token)->postJson('/api/staff/'.$endpoint, $payload);
        if ($valid) {
            $response->assertOk();
            $this->assertTrue(Hash::check($password, $user->fresh()->password));
            $this->assertFalse($user->fresh()->requires_password_change);
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->assertTrue(Hash::check('TempPass123!', $user->fresh()->password));
            $this->assertTrue($user->fresh()->requires_password_change);
            $this->assertDatabaseCount('user_security_questions', 0);
        }
    }

    public static function passwordLengths(): array
    {
        $cases = [];
        foreach (['password-setup', 'security-setup'] as $endpoint) {
            $cases[$endpoint.' too short'] = [$endpoint, 'Short1!', false];
            $cases[$endpoint.' minimum'] = [$endpoint, 'NewPass1', true];
            $cases[$endpoint.' maximum'] = [$endpoint, 'Personal123!', true];
            $cases[$endpoint.' too long'] = [$endpoint, 'Personal123!x', false];
        }

        return $cases;
    }
}
