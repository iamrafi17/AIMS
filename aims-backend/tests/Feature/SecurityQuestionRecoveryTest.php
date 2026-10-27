<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\College;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\SecurityQuestion;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityQuestionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_questions_are_available_without_exposing_answers(): void
    {
        $this->getJson('/api/security-questions')
            ->assertOk()
            ->assertJsonCount(12, 'questions')
            ->assertJsonStructure(['questions' => [['id', 'question']]])
            ->assertJsonMissing(['answer' => true])
            ->assertJsonMissing(['answer_hash' => true]);
    }

    public function test_registration_requires_three_different_questions_and_three_answers(): void
    {
        [$payload] = $this->registrationContext('2026-SEC-001');
        $questions = SecurityQuestion::query()->take(3)->get();

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('security_questions');

        $payload['security_questions'] = [
            ['security_question_id' => $questions[0]->id, 'answer' => 'Pizza'],
            ['security_question_id' => $questions[0]->id, 'answer' => 'Basketball'],
            ['security_question_id' => $questions[2]->id, 'answer' => ''],
        ];

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'security_questions.1.security_question_id',
                'security_questions.2.answer',
            ]);

        $this->assertDatabaseMissing('students', ['student_id' => '2026-SEC-001']);
        $this->assertDatabaseCount('user_security_questions', 0);
    }

    public function test_registration_saves_exactly_three_hashed_security_answers(): void
    {
        [$payload] = $this->registrationContext('2026-SEC-002');
        $questions = SecurityQuestion::query()->take(3)->get();
        $plainAnswers = ['Chicken Adobo', 'Volleyball', 'Reading Books'];
        $payload['security_questions'] = $questions->values()->map(
            fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => $plainAnswers[$index],
            ],
        )->all();

        $response = $this->postJson('/api/register', $payload)
            ->assertCreated()
            ->assertJsonMissing(['security_questions' => true])
            ->assertJsonMissing(['answer' => true])
            ->assertJsonMissing(['answer_hash' => true]);

        $user = User::findOrFail($response->json('user.id'));
        $saved = $user->securityQuestions()->orderBy('position')->get();

        $this->assertCount(3, $saved);
        foreach ($saved as $index => $savedQuestion) {
            $this->assertSame($questions[$index]->id, $savedQuestion->security_question_id);
            $this->assertNotSame($plainAnswers[$index], $savedQuestion->answer_hash);
            $this->assertTrue($savedQuestion->answer_case_sensitive);
            $this->assertTrue(Hash::check($plainAnswers[$index], $savedQuestion->answer_hash));
        }
    }

    public function test_invalid_and_legacy_accounts_do_not_reveal_whether_an_id_exists(): void
    {
        $invalid = $this->postJson('/api/forgot-password', ['user_id' => 'NOT-A-USER'])
            ->assertOk()
            ->assertJsonCount(3, 'questions')
            ->assertJsonStructure(['challenge_token', 'questions'])
            ->assertJsonPath('message', 'If this account is eligible for recovery, answer the security questions.');

        $legacyUser = User::factory()->create(['role' => 'coordinator']);

        $legacy = $this->postJson('/api/forgot-password', ['user_id' => $legacyUser->username])
            ->assertOk()
            ->assertJsonCount(3, 'questions')
            ->assertJsonStructure(['challenge_token', 'questions'])
            ->assertJsonPath('message', 'If this account is eligible for recovery, answer the security questions.');

        $this->assertSame($invalid->status(), $legacy->status());

        $this->postJson('/api/forgot-password/verify', [
            'user_id' => 'NOT-A-USER',
            'challenge_token' => $invalid->json('challenge_token'),
            'answers' => collect($invalid->json('questions'))->map(fn ($question) => [
                'security_question_id' => $question['id'],
                'answer' => 'decoy answer',
            ])->all(),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The recovery answers or session are invalid.');
    }

    public function test_all_answers_must_be_correct_before_password_can_be_reset(): void
    {
        [$user, $student, $questions, $answers, $oldPassword] = $this->registeredStudent('2026-SEC-003');

        $challenge = $this->postJson('/api/forgot-password', ['user_id' => $student->student_id])
            ->assertOk()
            ->assertJsonCount(3, 'questions')
            ->assertJsonPath('questions.0.id', $questions[0]->id)
            ->assertJsonPath('questions.1.id', $questions[1]->id)
            ->assertJsonPath('questions.2.id', $questions[2]->id)
            ->assertJsonMissing(['answer' => true])
            ->assertJsonMissing(['answer_hash' => true])
            ->json('challenge_token');

        $this->postJson('/api/reset-password', [
            'user_id' => $student->student_id,
            'token' => $challenge,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable();

        $wrongAnswers = $this->answerPayload($questions, $answers);
        $wrongAnswers[1]['answer'] = 'Definitely Wrong';

        $this->postJson('/api/forgot-password/verify', [
            'user_id' => $student->student_id,
            'challenge_token' => $challenge,
            'answers' => $wrongAnswers,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'One or more security answers are incorrect.');

        $wrongCase = $this->answerPayload($questions, $answers);
        $wrongCase[0]['answer'] = mb_strtolower($answers[0]);
        $this->postJson('/api/forgot-password/verify', [
            'user_id' => $student->student_id,
            'challenge_token' => $challenge,
            'answers' => $wrongCase,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'One or more security answers are incorrect.');

        $resetToken = $this->postJson('/api/forgot-password/verify', [
            'user_id' => $student->student_id,
            'challenge_token' => $challenge,
            'answers' => $this->answerPayload($questions, $answers),
        ])->assertOk()
            ->assertJsonStructure(['reset_token'])
            ->json('reset_token');

        $this->postJson('/api/reset-password', [
            'user_id' => $student->student_id,
            'token' => $resetToken,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'does-not-match',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        SystemSetting::where('key', 'minimum_password_length')->update(['value' => '14']);
        $this->postJson('/api/reset-password', [
            'user_id' => $student->student_id,
            'token' => $resetToken,
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));

        $this->postJson('/api/reset-password', [
            'user_id' => $student->student_id,
            'token' => $resetToken,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk()
            ->assertJsonPath('message', 'Your password has been successfully reset.');

        $this->assertFalse(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));

        $this->postJson('/api/login', [
            'login' => $student->student_id,
            'password' => $oldPassword,
            'device_name' => 'recovery-test',
        ])->assertUnprocessable();

        $this->postJson('/api/login', [
            'login' => $student->student_id,
            'password' => 'NewPassword123!',
            'device_name' => 'recovery-test',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_recovery_challenge_expires_after_fifteen_minutes(): void
    {
        [, $student, $questions, $answers] = $this->registeredStudent('2026-SEC-EXPIRE');
        $this->freezeTime();
        $challenge = $this->postJson('/api/forgot-password', ['user_id' => $student->student_id])
            ->assertOk()->json('challenge_token');
        $this->travel(15)->minutes();

        $this->postJson('/api/forgot-password/verify', [
            'user_id' => $student->student_id,
            'challenge_token' => $challenge,
            'answers' => $this->answerPayload($questions, $answers),
        ])->assertUnprocessable();
    }

    public function test_verified_reset_token_expires_without_changing_password_or_revoking_sessions(): void
    {
        [$user, $student, $questions, $answers, $oldPassword] = $this->registeredStudent('2026-SEC-STALE');
        $this->freezeTime();
        $tokenId = $user->createToken('existing-session')->accessToken->id;
        $challenge = $this->postJson('/api/forgot-password', ['user_id' => $student->student_id])
            ->assertOk()->json('challenge_token');
        $resetToken = $this->postJson('/api/forgot-password/verify', [
            'user_id' => $student->student_id,
            'challenge_token' => $challenge,
            'answers' => $this->answerPayload($questions, $answers),
        ])->assertOk()->json('reset_token');
        $this->travel(24)->hours();

        $this->postJson('/api/reset-password', [
            'user_id' => $student->student_id,
            'token' => $resetToken,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
    }

    private function registeredStudent(string $studentId): array
    {
        [$payload] = $this->registrationContext($studentId);
        $questions = SecurityQuestion::query()->take(3)->get();
        $answers = ['Sinigang', 'Badminton', 'Photography'];
        $payload['security_questions'] = $questions->values()->map(
            fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => $answers[$index],
            ],
        )->all();

        $response = $this->postJson('/api/register', $payload)->assertCreated();
        $user = User::findOrFail($response->json('user.id'));
        $student = $user->student;
        $student->update(['registration_status' => 'approved']);

        return [$user, $student, $questions, $answers, $payload['password']];
    }

    /**
     * @param Collection<int, SecurityQuestion> $questions
     */
    private function answerPayload(Collection $questions, array $answers): array
    {
        return $questions->values()->map(
            fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => $answers[$index],
            ],
        )->all();
    }

    private function registrationContext(string $studentId): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $college = College::create([
            'name' => 'Computing',
            'code' => 'CICS',
            'required_ojt_hours' => 600,
            'is_active' => true,
        ]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'BSIT',
            'is_active' => true,
        ]);
        $coordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        $section = AcademicSection::create([
            'academic_period_id' => AcademicPeriod::firstOrCreate([
                'academic_year' => '2026-2027', 'semester' => '1st Semester',
            ], ['start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'status' => 'draft'])->id,
            'program_id' => $program->id,
            'name' => 'BSIT 4A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        OjtEnrollment::create([
            'school_id' => $studentId,
            'first_name' => 'Security Test', 'last_name' => 'Student',
            'section' => 'BSIT 4A',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $section->id,
            'source' => 'manual',
            'added_by' => $coordinator->id,
        ]);

        return [[
            'student_id' => $studentId,
            'first_name' => 'Security',
            'last_name' => 'Student',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'contact_number' => '09171234567',
            'email' => strtolower($studentId).'@student.example.com',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'BSIT 4A',
            'parent_name' => 'Test Parent',
            'parent_relationship' => 'Mother',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09179876543',
            'hte_id' => null,
            'internship_semester' => 'First Semester',
            'internship_year' => '2026-2027',
            'agree_terms' => true,
            'agree_privacy' => true,
            'password' => 'Student1234!',
            'password_confirmation' => 'Student1234!',
        ]];
    }
}
