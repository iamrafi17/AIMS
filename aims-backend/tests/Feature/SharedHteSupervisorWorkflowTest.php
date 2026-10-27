<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Program;
use App\Models\ProgramOjtSetting;
use App\Models\SecurityQuestion;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SharedHteSupervisorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_programs_search_link_and_reuse_shared_hte_department_and_supervisor_records(): void
    {
        [$firstCoordinator, $secondCoordinator] = $this->coordinators();
        Sanctum::actingAs($firstCoordinator);

        $hteId = $this->postJson('/api/coordinator/htes', [
            ...$this->htePayload(),
            'departments' => [[
                'department_name' => 'Learning Resources Center',
                'supervisor_full_name' => 'Maria Saint',
                'supervisor_email' => 'maria.saint@example.com',
            ]],
        ])->assertCreated()
            ->assertJsonPath('hte.departments.0.department_name', 'Learning Resources Center')
            ->assertJsonPath('hte.departments.0.supervisors.0.full_name', 'Maria Saint')
            ->assertJsonPath('hte.departments.0.supervisors.0.contact_email', 'maria.saint@example.com')
            ->json('hte.id');

        $supervisor = Supervisor::whereHas('supervisorProfile', fn ($profile) => $profile->where('full_name', 'Maria Saint'))->firstOrFail();
        $this->assertDatabaseHas('supervisor_profiles', [
            'id' => $supervisor->supervisor_profile_id,
            'full_name' => 'Maria Saint',
            'contact_email' => 'maria.saint@example.com',
        ]);
        $this->assertDatabaseHas('hte_supervisor_assignments', [
            'supervisor_profile_id' => $supervisor->supervisor_profile_id,
            'hte_department_id' => $supervisor->hte_department_id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($secondCoordinator);
        $this->getJson('/api/coordinator/htes/search?search=Marinduque%20State')
            ->assertOk()
            ->assertJsonCount(1, 'htes')
            ->assertJsonPath('htes.0.id', $hteId)
            ->assertJsonPath('htes.0.linked_to_program', false)
            ->assertJsonPath('htes.0.departments.0.supervisors.0.full_name', 'Maria Saint');

        $this->postJson("/api/coordinator/htes/{$hteId}/link")
            ->assertOk();

        $this->postJson("/api/coordinator/htes/{$hteId}/departments", [
            'department_name' => '  learning   resources center ',
            'supervisor_full_name' => ' maria saint ',
            'supervisor_email' => 'maria.updated@example.com',
        ])->assertOk()
            ->assertJsonPath('message', 'Existing department and supervisor reused.');
        $this->assertSame('maria.updated@example.com', $supervisor->fresh()->contact_email);

        Sanctum::actingAs($firstCoordinator);
        $this->postJson("/api/coordinator/htes/{$hteId}/departments", [
            'department_name' => 'CICS Development Office',
            'supervisor_full_name' => 'CICS Supervisor',
        ])->assertCreated();
        $restrictedDepartmentId = (int) $this->app['db']->table('hte_departments')
            ->where('department_name', 'CICS Development Office')
            ->value('id');
        $this->assertDatabaseHas('hte_department_programs', [
            'hte_department_id' => $restrictedDepartmentId,
            'program_id' => $firstCoordinator->program_id,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('hte_department_programs', [
            'hte_department_id' => $restrictedDepartmentId,
            'program_id' => $secondCoordinator->program_id,
        ]);

        Sanctum::actingAs($secondCoordinator);
        $this->getJson("/api/coordinator/htes/{$hteId}")
            ->assertOk()
            ->assertJsonCount(1, 'departments')
            ->assertJsonPath('departments.0.department_name', 'Learning Resources Center');

        $this->postJson('/api/coordinator/htes', [
            ...$this->htePayload(),
            'name' => '  MARINDUQUE   STATE UNIVERSITY ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('htes', 1);
        $this->assertDatabaseCount('hte_departments', 2);
        $this->assertFalse(Schema::hasTable('supervisors'));
        $this->assertDatabaseCount('supervisor_profiles', 2);
        $this->assertDatabaseCount('hte_supervisor_assignments', 2);
        $this->assertDatabaseHas('hte_programs', [
            'hte_id' => $hteId,
            'program_id' => $firstCoordinator->program_id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('hte_programs', [
            'hte_id' => $hteId,
            'program_id' => $secondCoordinator->program_id,
            'is_active' => true,
        ]);
        $departmentId = (int) $this->app['db']->table('hte_departments')
            ->where('department_name', 'Learning Resources Center')
            ->value('id');
        $this->assertDatabaseHas('hte_department_programs', [
            'hte_department_id' => $departmentId,
            'program_id' => $firstCoordinator->program_id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('hte_department_programs', [
            'hte_department_id' => $departmentId,
            'program_id' => $secondCoordinator->program_id,
            'is_active' => true,
        ]);
        $this->assertFalse(Schema::hasColumn('htes', 'college_id'));
        $this->assertFalse(Schema::hasColumn('htes', 'program_id'));
        $this->assertFalse(Schema::hasColumn('hte_programs', 'college_id'));

        $this->getJson('/api/coordinator/htes')
            ->assertOk()
            ->assertJsonCount(1, 'htes')
            ->assertJsonPath('htes.0.id', $hteId);
    }

    public function test_admin_links_one_login_account_to_an_existing_supervisor_and_blocks_duplicates(): void
    {
        [$coordinator] = $this->coordinators();
        Sanctum::actingAs($coordinator);
        $hteId = $this->postJson('/api/coordinator/htes', [
            ...$this->htePayload(),
            'departments' => [[
                'department_name' => 'Learning Resources Center',
                'supervisor_full_name' => 'Maria Saint',
                'supervisor_email' => 'maria.saint@example.com',
            ]],
        ])->assertCreated()->json('hte.id');
        $supervisor = Supervisor::firstOrFail();

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/supervisor-assignments?search=Learning%20Resources')
            ->assertOk()
            ->assertJsonPath('supervisors.0.id', $supervisor->id)
            ->assertJsonPath('supervisors.0.full_name', 'Maria Saint')
            ->assertJsonPath('supervisors.0.contact_email', 'maria.saint@example.com')
            ->assertJsonPath('supervisors.0.hte.id', $hteId)
            ->assertJsonPath('supervisors.0.has_account', false);

        $created = $this->postJson('/api/admin/users', [
            'email' => 'maria.saint@example.com',
            'role' => 'supervisor',
            'supervisor_record_id' => $supervisor->id,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('user.name', 'Maria Saint')
            ->assertJsonPath('user.role', 'supervisor')
            ->assertJsonPath('user.supervisor_profile.id', $supervisor->id);

        $userId = $created->json('user.id');
        $this->assertDatabaseHas('supervisor_profiles', ['id' => $supervisor->supervisor_profile_id, 'user_id' => $userId]);

        $this->postJson('/api/admin/users', [
            'email' => 'another.supervisor@example.com',
            'role' => 'supervisor',
            'supervisor_record_id' => $supervisor->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('supervisor_record_id')
            ->assertJsonPath('errors.supervisor_record_id.0', 'Supervisor already has an account.');

        $this->assertDatabaseCount('users', 4);
    }

    public function test_shared_hte_keeps_moa_records_separate_for_each_program(): void
    {
        Storage::fake('local');
        [$firstCoordinator, $secondCoordinator] = $this->coordinators();
        Sanctum::actingAs($firstCoordinator);
        $hteId = $this->postJson('/api/coordinator/htes', $this->htePayload())
            ->assertCreated()
            ->json('hte.id');

        Sanctum::actingAs($secondCoordinator);
        $this->postJson("/api/coordinator/htes/{$hteId}/link")->assertOk();

        foreach ([$firstCoordinator, $secondCoordinator] as $index => $coordinator) {
            Sanctum::actingAs($coordinator);
            $this->post('/api/coordinator/htes/moas', [
                'hte_id' => $hteId,
                'program_id' => $coordinator->program_id,
                'effective_date' => '2026-08-01',
                'expiration_date' => '2027-08-01',
                'file' => UploadedFile::fake()->create('agreement-'.($index + 1).'.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->assertDatabaseCount('moas', 2);
        $this->assertDatabaseHas('moas', ['hte_id' => $hteId, 'program_id' => $firstCoordinator->program_id]);
        $this->assertDatabaseHas('moas', ['hte_id' => $hteId, 'program_id' => $secondCoordinator->program_id]);
    }

    public function test_deployment_only_accepts_a_supervisor_from_the_selected_hte_department(): void
    {
        [$coordinator] = $this->coordinators();
        Sanctum::actingAs($coordinator);
        $hteId = $this->postJson('/api/coordinator/htes', [
            ...$this->htePayload(),
            'departments' => [[
                'department_name' => 'Learning Resources Center',
                'supervisor_full_name' => 'Maria Saint',
            ]],
        ])->assertCreated()->json('hte.id');

        $this->postJson("/api/coordinator/htes/{$hteId}/departments", [
            'department_name' => 'Accounting Office',
            'supervisor_full_name' => 'Pedro Reyes',
        ])->assertCreated();

        $learningSupervisor = Supervisor::whereHas('supervisorProfile', fn ($profile) => $profile->where('full_name', 'Maria Saint'))->firstOrFail();
        $accountingSupervisor = Supervisor::whereHas('supervisorProfile', fn ($profile) => $profile->where('full_name', 'Pedro Reyes'))->firstOrFail();
        $studentUser = User::factory()->create(['role' => 'student']);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-5001',
            'first_name' => 'Deployment',
            'last_name' => 'Student',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Boac, Marinduque',
            'contact_number' => '09170000001',
            'college_id' => $coordinator->college_id,
            'program_id' => $coordinator->program_id,
            'academic_period_id' => $this->academicPeriod()->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Parent Name',
            'parent_address' => 'Boac, Marinduque',
            'parent_contact_number' => '09170000002',
            'registration_status' => 'approved',
        ]);
        \Illuminate\Support\Facades\DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $student->academic_section_id, 'coordinator_id' => $coordinator->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        ProgramOjtSetting::create([
            'program_id' => $student->program_id,
            'academic_period_id' => $student->academic_period_id,
            'required_hours' => 486, 'ojt_start_date' => '2026-09-01',
            'status' => 'published', 'published_at' => now(),
        ]);

        $payload = [
            'hte_id' => $hteId,
            'hte_department_id' => $learningSupervisor->hte_department_id,
            'supervisor_record_id' => $accountingSupervisor->id,
            'ojt_start_date' => '2026-09-01',
            'ojt_end_date' => '2027-01-31',
            'required_ojt_hours' => 486,
            'official_am_start' => '08:00',
            'official_am_end' => '12:00',
            'official_pm_start' => '13:00',
            'official_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'internship_status' => 'active',
            'allow_past_attendance' => false,
        ];

        $this->putJson("/api/coordinator/htes/deployments/{$student->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supervisor_record_id');

        $payload['supervisor_record_id'] = $learningSupervisor->id;
        $this->putJson("/api/coordinator/htes/deployments/{$student->id}", $payload)
            ->assertOk()
            ->assertJsonPath('student.hte_department_id', $learningSupervisor->hte_department_id)
            ->assertJsonPath('student.supervisor_record_id', $learningSupervisor->id)
            ->assertJsonPath('student.supervisor_record.full_name', 'Maria Saint');
    }

    public function test_supervisor_first_login_exposes_read_only_assignment_and_only_onboards_once(): void
    {
        [$coordinator] = $this->coordinators();
        Sanctum::actingAs($coordinator);
        $this->postJson('/api/coordinator/htes', [
            ...$this->htePayload(),
            'departments' => [[
                'department_name' => 'Learning Resources Center',
                'supervisor_full_name' => 'Maria Saint',
            ]],
        ])->assertCreated();
        $supervisor = Supervisor::firstOrFail();

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/admin/users', [
            'email' => 'maria.saint@example.com',
            'role' => 'supervisor',
            'supervisor_record_id' => $supervisor->id,
        ])->assertCreated();

        $userId = $created->json('user.username');
        $temporaryPassword = $created->json('temporary_password');
        $this->app['auth']->forgetGuards();

        $login = $this->postJson('/api/login', [
            'login' => $userId,
            'password' => $temporaryPassword,
            'device_name' => 'Supervisor onboarding test',
        ])->assertOk()
            ->assertJsonPath('user.onboarding_step', 'complete_profile')
            ->assertJsonPath('user.supervisor_assignment.full_name', 'Maria Saint')
            ->assertJsonPath('user.supervisor_assignment.hte.name', 'Marinduque State University')
            ->assertJsonPath('user.supervisor_assignment.department.department_name', 'Learning Resources Center');
        $token = $login->json('token');

        $this->withToken($token)->postJson('/api/staff/profile-setup', [
            'email' => 'maria.saint@example.com',
            'contact_number' => '09171234567',
            'address' => 'Boac, Marinduque',
        ])->assertOk()
            ->assertJsonPath('user.supervisor_assignment.hte.address', 'Matalaba, Santa Cruz, Marinduque');

        $questions = SecurityQuestion::query()->take(3)->get();
        $this->withToken($token)->postJson('/api/staff/security-setup', [
            'password' => 'Personal123!',
            'password_confirmation' => 'Personal123!',
            'security_questions' => $questions->values()->map(fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => 'Supervisor answer '.($index + 1),
            ])->all(),
        ])->assertOk()->assertJsonPath('onboarding_step', null);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', [
            'login' => $userId,
            'password' => 'Personal123!',
            'device_name' => 'Supervisor second login',
        ])->assertOk()
            ->assertJsonPath('user.onboarding_step', null)
            ->assertJsonPath('user.requires_profile_setup', false)
            ->assertJsonPath('user.requires_security_setup', false);

        $challenge = $this->postJson('/api/forgot-password', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonCount(3, 'questions')
            ->json('challenge_token');
        $resetToken = $this->postJson('/api/forgot-password/verify', [
            'user_id' => $userId,
            'challenge_token' => $challenge,
            'answers' => $questions->values()->map(fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => 'Supervisor answer '.($index + 1),
            ])->all(),
        ])->assertOk()->json('reset_token');
        $this->postJson('/api/reset-password', [
            'user_id' => $userId,
            'token' => $resetToken,
            'password' => 'RecoveredPass123!',
            'password_confirmation' => 'RecoveredPass123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('RecoveredPass123!', User::findOrFail($created->json('user.id'))->password));
    }

    private function coordinators(): array
    {
        $college = College::create(['name' => 'College of Computing', 'code' => 'CICS', 'is_active' => true]);
        $firstProgram = Program::create(['college_id' => $college->id, 'name' => 'Information Systems', 'code' => 'BSIS', 'is_active' => true]);
        $secondProgram = Program::create(['college_id' => $college->id, 'name' => 'Political Science', 'code' => 'BAPS', 'is_active' => true]);

        return [
            User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $firstProgram->id]),
            User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $secondProgram->id]),
        ];
    }

    private function htePayload(): array
    {
        return [
            'name' => 'Marinduque State University',
            'address' => 'Matalaba, Santa Cruz, Marinduque',
            'contact_person' => 'Juan Dela Cruz',
            'contact_email' => 'juan.delacruz@example.com',
            'contact_number' => '09170000000',
            'latitude' => 13.475,
            'longitude' => 122.027,
            'geofence_radius' => 150,
            'geofence_enabled' => true,
            'default_am_start' => '08:00',
            'default_am_end' => '12:00',
            'default_pm_start' => '13:00',
            'default_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
        ];
    }
}
