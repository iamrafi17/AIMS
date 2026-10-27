<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Program;
use App\Models\SecurityQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffSecuritySetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_staff_completes_profile_then_security_and_password_before_dashboard(): void
    {
        $college = College::create([
            'name' => 'College of Information and Computing Sciences',
            'code' => 'CICS',
            'required_ojt_hours' => 600,
            'is_active' => true,
        ]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Systems',
            'code' => 'BSIS',
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/admin/users', [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan.delacruz@marsu.edu.ph',
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ])->assertCreated()
            ->assertJsonPath('user.email', 'juan.delacruz@marsu.edu.ph')
            ->assertJsonPath('user.requires_password_change', true)
            ->assertJsonPath('user.requires_security_setup', true)
            ->assertJsonPath('user.requires_profile_setup', true);

        $userId = $created->json('user.username');
        $temporaryPassword = $created->json('temporary_password');
        $this->assertMatchesRegularExpression('/[A-Z]/', $temporaryPassword);
        $this->assertMatchesRegularExpression('/[a-z]/', $temporaryPassword);
        $this->assertMatchesRegularExpression('/\d/', $temporaryPassword);
        $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $temporaryPassword);
        $this->app['auth']->forgetGuards();
        $login = $this->postJson('/api/login', [
            'login' => $userId,
            'password' => $temporaryPassword,
            'device_name' => 'Onboarding flow test',
        ])->assertOk()
            ->assertJsonPath('user.onboarding_step', 'complete_profile');
        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/coordinator/dashboard')
            ->assertForbidden()
            ->assertJsonPath('onboarding_step', 'complete_profile');

        $this->withToken($token)->postJson('/api/staff/profile-setup', [
            'email' => 'juan.delacruz@marsu.edu.ph',
            'contact_number' => '0917123456',
            'address' => 'Santa Cruz, Marinduque',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('contact_number');

        $this->withToken($token)->postJson('/api/staff/profile-setup', [
            'email' => 'juan.delacruz@marsu.edu.ph',
            'contact_number' => '09171234567',
            'address' => 'Santa Cruz, Marinduque',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Juan Dela Cruz')
            ->assertJsonPath('user.onboarding_step', 'security_setup');

        $this->withToken($token)->getJson('/api/coordinator/dashboard')
            ->assertForbidden()
            ->assertJsonPath('onboarding_step', 'security_setup');

        $profileCompletedAt = User::where('username', $userId)->firstOrFail()->profile_completed_at;
        $this->withToken($token)->postJson('/api/staff/profile-setup', [
            'email' => 'juan.delacruz@marsu.edu.ph',
            'contact_number' => '09179998888',
            'address' => 'Boac, Marinduque',
        ])->assertOk()
            ->assertJsonPath('user.onboarding_step', 'security_setup')
            ->assertJsonPath('user.contact_number', '09179998888');
        $this->assertEquals($profileCompletedAt, User::where('username', $userId)->firstOrFail()->profile_completed_at);

        $questions = SecurityQuestion::query()->take(3)->get();
        $this->withToken($token)->postJson('/api/staff/security-setup', [
            'password' => 'Personal123!',
            'password_confirmation' => 'Personal123!',
            'security_questions' => $questions->values()->map(fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => 'Private answer '.($index + 1),
            ])->all(),
        ])->assertOk()
            ->assertJsonPath('onboarding_step', null);

        $this->withToken($token)->getJson('/api/coordinator/dashboard')->assertOk();

        $staff = User::where('username', $userId)->firstOrFail();
        $this->assertSame('juan.delacruz@marsu.edu.ph', $staff->email);
        $this->assertSame('09179998888', $staff->contact_number);
        $this->assertSame('Boac, Marinduque', $staff->address);
        $this->assertNotNull($staff->profile_completed_at);
        $this->assertFalse($staff->requiresStaffOnboarding());

        $this->withToken($token)->postJson('/api/staff/profile-setup', [
            'email' => 'juan.delacruz@marsu.edu.ph',
            'contact_number' => '09171234567',
            'address' => 'Santa Cruz, Marinduque',
        ])->assertForbidden();
    }

    public function test_all_supported_staff_roles_must_finish_first_login_setup_before_dashboard_access(): void
    {
        $college = College::create([
            'name' => 'College of Information and Computing Sciences',
            'code' => 'CICS',
            'required_ojt_hours' => 600,
            'is_active' => true,
        ]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Systems',
            'code' => 'BSIS',
            'is_active' => true,
        ]);
        $questions = SecurityQuestion::query()->take(3)->get();
        $answers = ['Sinigang', 'Badminton', 'Marinduque'];
        $roleDashboards = [
            'coordinator' => '/api/coordinator/dashboard',
            'program_head' => '/api/program-head/dashboard',
            'vpaa' => '/api/vpaa/dashboard',
            'supervisor' => '/api/supervisor/dashboard',
        ];

        foreach ($roleDashboards as $role => $dashboard) {
            $user = User::factory()->create([
                'role' => $role,
                'password' => 'TempPass123!',
                'requires_security_setup' => true,
                'college_id' => in_array($role, ['coordinator', 'program_head'], true) ? $college->id : null,
                'program_id' => in_array($role, ['coordinator', 'program_head'], true) ? $program->id : null,
            ]);

            $login = $this->postJson('/api/login', [
                'login' => $user->username,
                'password' => 'TempPass123!',
                'device_name' => 'First login test',
            ])->assertOk()
                ->assertJsonPath('user.requires_security_setup', true);

            $token = $login->json('token');
            $this->app['auth']->forgetGuards();

            $blockedDashboard = $this->withToken($token)->getJson($dashboard)
                ->assertForbidden();
            $this->assertTrue(
                $blockedDashboard->json('requires_security_setup'),
                $role.': '.$blockedDashboard->getContent(),
            );

            $payload = [
                'password' => 'Personal123!',
                'password_confirmation' => 'Personal123!',
                'security_questions' => $questions->values()->map(
                    fn (SecurityQuestion $question, int $index) => [
                        'security_question_id' => $question->id,
                        'answer' => $answers[$index],
                    ],
                )->all(),
            ];

            $this->withToken($token)->postJson('/api/staff/security-setup', $payload)
                ->assertOk()
                ->assertJsonPath('requires_security_setup', false);

            $this->withToken($token)->getJson($dashboard)->assertOk();

            $saved = $user->fresh()->securityQuestions()->orderBy('position')->get();
            $this->assertCount(3, $saved);
            $this->assertFalse($user->fresh()->requires_security_setup);
            $this->assertTrue(Hash::check('Personal123!', $user->fresh()->password));

            foreach ($saved as $index => $savedQuestion) {
                $this->assertTrue($savedQuestion->answer_case_sensitive);
                $this->assertTrue(Hash::check($answers[$index], $savedQuestion->answer_hash));
            }

            $this->app['auth']->forgetGuards();
        }
    }

    public function test_staff_setup_requires_three_different_questions_and_a_new_personal_password(): void
    {
        $user = User::factory()->create([
            'role' => 'supervisor',
            'password' => 'TempPass123!',
            'requires_security_setup' => true,
        ]);
        $questions = SecurityQuestion::query()->take(3)->get();
        $token = $this->postJson('/api/login', [
            'login' => $user->username,
            'password' => 'TempPass123!',
            'device_name' => 'Validation test',
        ])->json('token');

        $duplicateQuestions = [
            ['security_question_id' => $questions[0]->id, 'answer' => 'Answer one'],
            ['security_question_id' => $questions[0]->id, 'answer' => 'Answer two'],
            ['security_question_id' => $questions[2]->id, 'answer' => 'Answer three'],
        ];

        $this->withToken($token)->postJson('/api/staff/security-setup', [
            'password' => 'Personal123!',
            'password_confirmation' => 'Personal123!',
            'security_questions' => $duplicateQuestions,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('security_questions.1.security_question_id');

        $validQuestions = $questions->values()->map(fn (SecurityQuestion $question, int $index) => [
            'security_question_id' => $question->id,
            'answer' => 'Private answer '.($index + 1),
        ])->all();

        $this->withToken($token)->postJson('/api/staff/security-setup', [
            'password' => 'TempPass123!',
            'password_confirmation' => 'TempPass123!',
            'security_questions' => $validQuestions,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertTrue($user->fresh()->requires_security_setup);
        $this->assertDatabaseCount('user_security_questions', 0);
    }

    public function test_staff_can_recover_password_with_user_id_after_security_setup(): void
    {
        $user = User::factory()->create([
            'role' => 'vpaa',
            'password' => 'TempPass123!',
            'requires_security_setup' => true,
        ]);
        $questions = SecurityQuestion::query()->take(3)->get();
        $answers = ['Adobo', 'Volleyball', 'Reading'];
        $token = $this->postJson('/api/login', [
            'login' => $user->username,
            'password' => 'TempPass123!',
            'device_name' => 'Recovery setup test',
        ])->json('token');

        $this->withToken($token)->postJson('/api/staff/security-setup', [
            'password' => 'Personal123!',
            'password_confirmation' => 'Personal123!',
            'security_questions' => $questions->values()->map(fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => $answers[$index],
            ])->all(),
        ])->assertOk();

        $challenge = $this->postJson('/api/forgot-password', ['user_id' => $user->username])
            ->assertOk()
            ->assertJsonPath('questions.0.id', $questions[0]->id)
            ->assertJsonPath('questions.1.id', $questions[1]->id)
            ->assertJsonPath('questions.2.id', $questions[2]->id)
            ->json('challenge_token');

        $this->postJson('/api/forgot-password/verify', [
            'user_id' => $user->username,
            'challenge_token' => $challenge,
            'answers' => $questions->values()->map(fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => mb_strtolower($answers[$index]),
            ])->all(),
        ])->assertUnprocessable();

        $resetToken = $this->postJson('/api/forgot-password/verify', [
            'user_id' => $user->username,
            'challenge_token' => $challenge,
            'answers' => $questions->values()->map(fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => $answers[$index],
            ])->all(),
        ])->assertOk()->json('reset_token');

        $this->postJson('/api/reset-password', [
            'user_id' => $user->username,
            'token' => $resetToken,
            'password' => 'RecoveredPass123!',
            'password_confirmation' => 'RecoveredPass123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('RecoveredPass123!', $user->fresh()->password));
    }
}
