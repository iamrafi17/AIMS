<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\ApprovalRecord;
use App\Models\College;
use App\Models\HTE;
use App\Models\HteDepartment;
use App\Models\InternshipRequirement;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\SecurityQuestion;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use App\Support\DeploymentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentDeploymentHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        config(['attendance.use_server_time' => true]);
        $this->travelTo(Carbon::parse('2026-10-22 08:00:00', 'Asia/Manila'));
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'IT', 'code' => 'BSIT', 'is_active' => true, 'smart_clock_enabled' => true]);
        $period = AcademicPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'status' => 'active']);
        $section = AcademicSection::create(['program_id' => $program->id, 'academic_period_id' => $period->id, 'name' => '4A', 'year_level' => 4, 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        $head = User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id]);
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $college->id]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);
        DB::table('coordinator_section_assignments')->insert(['academic_section_id' => $section->id, 'coordinator_id' => $coordinator->id, 'created_at' => now(), 'updated_at' => now()]);
        $hte = HTE::create(['name' => 'ABC Company', 'address' => 'Santa Cruz', 'contact_person' => 'Mr. Santos',
            'contact_email' => 'abc@example.com', 'contact_number' => '09171234567', 'is_active' => true]);
        $hte->programs()->attach($program->id, ['is_active' => true]);
        $department = HteDepartment::create(['hte_id' => $hte->id, 'department_name' => 'IT Department', 'normalized_name' => 'it department']);
        $department->programs()->attach($program->id, ['is_active' => true]);
        $supervisor = Supervisor::create(['hte_department_id' => $department->id, 'full_name' => 'Mr. Santos', 'is_active' => true]);
        OjtEnrollment::create(['school_id' => '2026-FLOW-001', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'academic_section_id' => $section->id, 'source' => 'manual', 'added_by' => $coordinator->id]);
        $response = $this->postJson('/api/register', [
            'student_id' => '2026-FLOW-001', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'gender' => 'male', 'birth_date' => '2003-01-01', 'address' => 'Santa Cruz', 'contact_number' => '09171234567',
            'email' => 'juan@example.com', 'section' => '4A', 'parent_name' => 'Parent Cruz', 'parent_relationship' => 'Father',
            'parent_address' => 'Santa Cruz', 'parent_contact_number' => '09171234568', 'hte_id' => $hte->id,
            'hte_department_id' => $department->id, 'agree_terms' => true, 'agree_privacy' => true,
            'password' => 'Student123!', 'password_confirmation' => 'Student123!',
            'security_questions' => SecurityQuestion::take(3)->get()->map(fn ($question) => ['security_question_id' => $question->id, 'answer' => 'Audit answer'])->all(),
        ])->assertCreated();
        $student = Student::findOrFail($response->json('student.id'));
        $payload = ['hte_id' => $hte->id, 'hte_department_id' => $department->id, 'supervisor_record_id' => $supervisor->id,
            'schedule_mode' => 'custom', 'official_am_start' => '08:00', 'official_am_end' => '12:00',
            'official_pm_start' => '13:00', 'official_pm_end' => '17:00', 'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'internship_status' => 'pending', 'allow_past_attendance' => false,
            'required_ojt_hours' => 1, 'ojt_start_date' => '2020-01-01'];
        Sanctum::actingAs($head);
        $this->postJson('/api/program-head/ojt-settings/publish', ['academic_period_id' => $period->id,
            'required_hours' => 486, 'ojt_start_date' => '2026-11-02'])->assertOk();

        return compact('student', 'coordinator', 'head', 'dean', 'vpaa', 'hte', 'department', 'supervisor', 'payload');
    }

    private function submit(array $context): ApprovalRecord
    {
        Sanctum::actingAs($context['coordinator']);
        $this->putJson('/api/coordinator/htes/deployments/'.$context['student']->id, $context['payload'])
            ->assertOk()->assertJsonPath('student.schedule_status', 'pending')
            ->assertJsonPath('student.ojt_start_date', fn ($value) => Carbon::parse($value)->setTimezone('Asia/Manila')->toDateString() === '2026-11-02');

        return ApprovalRecord::where('subject_type', 'deployment')->latest('id')->firstOrFail();
    }

    public function test_registration_preference_is_confirmed_reviewed_forwarded_and_visible_only_after_dean(): void
    {
        $c = $this->context();
        $this->assertNull($c['student']->hte_id);
        $this->assertSame($c['hte']->id, $c['student']->currentInternship->preferred_hte_id);
        Sanctum::actingAs($c['coordinator']);
        $this->getJson('/api/coordinator/htes')->assertOk()
            ->assertJsonPath('deployments.0.current_internship.preferred_department.department_name', 'IT Department');
        $approval = $this->submit($c);
        $this->assertSame(486.0, (float) $c['student']->fresh()->required_ojt_hours);
        Sanctum::actingAs($c['dean']->fresh());
        $this->postJson('/api/dean/deployments/'.$approval->id.'/forward')->assertUnprocessable();
        Sanctum::actingAs($c['vpaa']);
        $this->getJson('/api/vpaa/deployments')->assertOk()->assertJsonCount(0, 'deployments');
        Sanctum::actingAs($c['head']);
        $this->getJson('/api/program-head/deployments')->assertOk()->assertJsonPath('deployments.0.department.name', 'IT Department');
        $this->putJson('/api/program-head/deployments/'.$approval->id.'/review', ['decision' => 'approved'])
            ->assertOk()->assertJsonPath('deployment.stage', DeploymentWorkflow::DEAN);
        $this->putJson('/api/program-head/deployments/'.$approval->id.'/review', ['decision' => 'approved'])->assertUnprocessable();
        $this->assertSame('approved', $c['student']->fresh()->schedule_status);
        $this->assertSame('pending', $c['student']->fresh()->internship_status);
        Sanctum::actingAs($c['vpaa']);
        $this->getJson('/api/vpaa/deployments')->assertJsonCount(0, 'deployments');
        Sanctum::actingAs($c['dean']->fresh());
        $this->getJson('/api/dean/deployments')->assertOk()->assertJsonPath('deployments.0.department.name', 'IT Department');
        $this->postJson('/api/dean/deployments/'.$approval->id.'/forward', ['decision' => 'rejected'])->assertUnprocessable();
        $this->postJson('/api/dean/deployments/'.$approval->id.'/forward')->assertOk()->assertJsonPath('deployment.stage', DeploymentWorkflow::VPAA);
        $this->postJson('/api/dean/deployments/'.$approval->id.'/forward')->assertUnprocessable();
        Sanctum::actingAs($c['vpaa']);
        $this->getJson('/api/vpaa/deployments')->assertOk()->assertJsonPath('deployments.0.hte.name', 'ABC Company')
            ->assertJsonPath('deployments.0.department.name', 'IT Department')->assertJsonPath('deployments.0.supervisor.name', 'Mr. Santos');
        $this->postJson('/api/dean/deployments/'.$approval->id.'/forward')->assertForbidden();
        $this->putJson('/api/program-head/deployments/'.$approval->id.'/review', ['decision' => 'approved'])->assertForbidden();
        $this->travelTo(Carbon::parse('2026-11-02 08:00:00', 'Asia/Manila'));
        Sanctum::actingAs($c['student']->user);
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonPath('student.internship_status', 'active')
            ->assertJsonPath('deployment.stage', DeploymentWorkflow::VPAA);
        $this->assertDatabaseHas('system_notifications', ['user_id' => $c['vpaa']->id, 'action_url' => '/vpaa/deployments']);
    }

    public function test_reassignment_preserves_preferences_and_old_snapshot_and_unchanged_save_preserves_approval(): void
    {
        $c = $this->context();
        $first = $this->submit($c);
        $other = HteDepartment::create(['hte_id' => $c['hte']->id, 'department_name' => 'Records Office', 'normalized_name' => 'records office']);
        $other->programs()->attach($c['student']->program_id, ['is_active' => true]);
        $c['payload']['hte_department_id'] = $other->id;
        $c['payload']['supervisor_record_id'] = null;
        $second = $this->submit($c);
        $this->assertSame(DeploymentWorkflow::SUPERSEDED, $first->fresh()->deployment_stage);
        $this->assertSame('IT Department', $first->fresh()->deployment_snapshot['department']['name']);
        $this->assertSame('Records Office', $second->deployment_snapshot['department']['name']);
        $this->assertSame($c['department']->id, $c['student']->fresh()->currentInternship->preferred_hte_department_id);
        Sanctum::actingAs($c['head']);
        $this->putJson('/api/program-head/deployments/'.$first->id.'/review', ['decision' => 'approved'])->assertUnprocessable();
        $this->putJson('/api/program-head/deployments/'.$second->id.'/review', ['decision' => 'approved'])->assertOk();
        Sanctum::actingAs($c['coordinator']);
        $this->putJson('/api/coordinator/htes/deployments/'.$c['student']->id, $c['payload'])
            ->assertOk()->assertJsonPath('student.schedule_status', 'approved');
        $this->assertSame(2, ApprovalRecord::where('subject_type', 'deployment')->count());
        $this->assertSame(DeploymentWorkflow::DEAN, $second->fresh()->deployment_stage);
    }

    public function test_other_program_and_college_cannot_review_or_forward_and_inactive_hte_is_blocked(): void
    {
        $c = $this->context();
        $approval = $this->submit($c);
        $college = College::create(['name' => 'Other', 'code' => 'OTH', 'is_active' => true]);
        $program = Program::create(['name' => 'Other Program', 'code' => 'OTHER', 'college_id' => $college->id, 'is_active' => true]);
        Sanctum::actingAs(User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id]));
        $this->getJson('/api/program-head/deployments')->assertOk()->assertJsonCount(0, 'deployments');
        $this->putJson('/api/program-head/deployments/'.$approval->id.'/review', ['decision' => 'approved'])->assertNotFound();
        Sanctum::actingAs(User::factory()->create(['role' => 'dean', 'college_id' => $college->id])->fresh());
        $this->getJson('/api/dean/deployments')->assertOk()->assertJsonCount(0, 'deployments');
        $this->postJson('/api/dean/deployments/'.$approval->id.'/forward')->assertNotFound();
        $c['hte']->update(['is_active' => false]);
        Sanctum::actingAs($c['coordinator']);
        $this->putJson('/api/coordinator/htes/deployments/'.$c['student']->id, $c['payload'])->assertUnprocessable();
    }

    public function test_new_deployment_cannot_clock_before_review_or_start_date(): void
    {
        $c = $this->context();
        $approval = $this->submit($c);
        $c['student']->fresh()->update(['consent_status' => 'done']);
        InternshipRequirement::where('student_id', $c['student']->id)->whereRaw('LOWER(requirement_name) LIKE ?', ['%consent%'])
            ->update(['status' => 'approved', 'file_path' => 'consent.pdf']);
        Sanctum::actingAs($c['student']->user);
        $clock = ['status' => 'present', 'work_mode' => 'wfh', 'session_type' => 'full_day', 'latitude' => 13.47, 'longitude' => 122.02];
        $this->postJson('/api/student/attendance/quick-clock', $clock)->assertForbidden();
        Sanctum::actingAs($c['head']);
        $this->putJson('/api/program-head/deployments/'.$approval->id.'/review', ['decision' => 'approved'])->assertOk();
        Sanctum::actingAs($c['student']->user);
        $this->postJson('/api/student/attendance/quick-clock', $clock)->assertForbidden();
        $this->travelTo(Carbon::parse('2026-11-02 08:00:00', 'Asia/Manila'));
        Http::fake();
        $this->postJson('/api/student/attendance/quick-clock', $clock)->assertOk();
    }

    public function test_existing_pending_deployments_are_adopted_without_inventing_dean_forwarding(): void
    {
        $c = $this->context();
        $origin = $this->submit($c);
        $origin->update(['deployment_stage' => null, 'deployment_snapshot' => null, 'deployment_internship_id' => null]);
        DeploymentWorkflow::adoptLegacyPending();
        $this->assertSame(DeploymentWorkflow::PROGRAM_HEAD, $origin->fresh()->deployment_stage);
        $origin->update(['status' => 'approved', 'decided_by' => $c['head']->id, 'decided_at' => now()]);
        $legacy = ApprovalRecord::submit('deployment', $c['student']->id, $c['head']->id);
        DeploymentWorkflow::adoptLegacyPending();
        DeploymentWorkflow::adoptLegacyPending();
        $legacy = $legacy->fresh();
        $this->assertSame(DeploymentWorkflow::DEAN, $legacy->deployment_stage);
        $this->assertSame('approved', $legacy->status);
        $this->assertSame($c['head']->id, $legacy->decided_by);
        $this->assertNull($legacy->dean_forwarded_at);
        $this->assertSame($c['coordinator']->name, DeploymentWorkflow::payload($legacy)['coordinator']);
        $this->assertSame(2, ApprovalRecord::where('subject_type', 'deployment')->count());
        Sanctum::actingAs($c['vpaa']);
        $this->getJson('/api/vpaa/deployments')->assertOk()->assertJsonCount(0, 'deployments');
    }

    public function test_forwarded_history_keeps_its_original_department_after_a_new_assignment(): void
    {
        $c = $this->context();
        $first = $this->submit($c);
        Sanctum::actingAs($c['head']);
        $this->putJson('/api/program-head/deployments/'.$first->id.'/review', ['decision' => 'approved'])->assertOk();
        Sanctum::actingAs($c['dean']->fresh());
        $this->postJson('/api/dean/deployments/'.$first->id.'/forward')->assertOk();
        $other = HteDepartment::create(['hte_id' => $c['hte']->id, 'department_name' => 'Records Office', 'normalized_name' => 'records office']);
        $other->programs()->attach($c['student']->program_id, ['is_active' => true]);
        $c['payload']['hte_department_id'] = $other->id;
        $c['payload']['supervisor_record_id'] = null;
        $second = $this->submit($c);
        Sanctum::actingAs($c['vpaa']);
        $this->getJson('/api/vpaa/deployments')->assertOk()->assertJsonCount(1, 'deployments')
            ->assertJsonPath('deployments.0.department.name', 'IT Department')->assertJsonPath('deployments.0.is_current', false);
        Sanctum::actingAs($c['dean']->fresh());
        $this->postJson('/api/dean/deployments/'.$second->id.'/forward')->assertUnprocessable();
        $this->assertSame($c['head']->id, $first->fresh()->decided_by);
        $this->assertSame($c['dean']->id, $first->fresh()->dean_forwarded_by);
    }
}
