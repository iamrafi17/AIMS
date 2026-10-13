<?php

namespace Tests\Feature;

use App\Models\SecurityQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimpleSecurityQuestionChoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_defaults_are_restored_for_an_empty_question_bank(): void
    {
        SecurityQuestion::query()->delete();
        $migration = require database_path('migrations/2026_09_18_000004_simplify_security_question_choices.php');
        $migration->up();
        $migration->up();

        $this->getJson('/api/security-questions')
            ->assertOk()
            ->assertJsonCount(12, 'questions')
            ->assertJsonFragment(['question' => 'What is your favorite color?']);
        $this->assertDatabaseCount('security_questions', 12);
    }

    public function test_simple_choices_are_available_without_duplicating_or_rewording_old_questions(): void
    {
        $original = SecurityQuestion::where('question', 'What nickname do your family and friends call you?')->firstOrFail();
        $migration = require database_path('migrations/2026_09_18_000004_simplify_security_question_choices.php');
        $migration->up();
        $migration->up();

        $this->getJson('/api/security-questions')
            ->assertOk()
            ->assertJsonCount(12, 'questions')
            ->assertJsonFragment(['question' => 'What is your nickname?'])
            ->assertJsonFragment(['question' => 'Who is your favorite singer?'])
            ->assertJsonFragment(['question' => 'What is your favorite food?'])
            ->assertJsonMissing(['question' => $original->question]);

        $this->assertDatabaseHas('security_questions', [
            'id' => $original->id,
            'question' => $original->question,
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('security_questions', 14);
    }

    public function test_existing_accounts_can_still_recover_using_retired_questions(): void
    {
        $user = User::factory()->create(['role' => 'coordinator']);
        $questions = SecurityQuestion::whereIn('question', [
            'What nickname do your family and friends call you?',
            'What type of music do you like the most?',
            'What is your favorite food?',
        ])->orderBy('id')->get();

        foreach ($questions as $index => $question) {
            $user->securityQuestions()->create([
                'security_question_id' => $question->id,
                'position' => $index + 1,
                'answer_hash' => Hash::make('my answer'),
            ]);
        }

        $challenge = $this->postJson('/api/forgot-password', ['user_id' => $user->username])
            ->assertOk()
            ->assertJsonCount(3, 'questions')
            ->assertJsonFragment(['question' => 'What nickname do your family and friends call you?'])
            ->assertJsonFragment(['question' => 'What type of music do you like the most?']);

        $this->postJson('/api/forgot-password/verify', [
            'user_id' => $user->username,
            'challenge_token' => $challenge->json('challenge_token'),
            'answers' => $questions->map(fn ($question) => [
                'security_question_id' => $question->id,
                'answer' => 'My Answer',
            ])->all(),
        ])->assertOk();
    }
}
