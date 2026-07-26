<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VPAAApprovalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_head_endorsement_can_be_reviewed_and_tracked_by_vpaa(): void
    {
        Storage::fake('public');
        [$student] = $this->studentContext();
        $programHead = User::factory()->create([
            'role' => 'program_head',
            'college_id' => $student->college_id,
            'program_id' => $student->program_id,
        ]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);
        Storage::disk('public')->put('requirements/endorsed.pdf', 'endorsed document');

        $requirement = InternshipRequirement::create([
            'student_id' => $student->id,
            'requirement_name' => 'Curriculum Vitae',
            'file_path' => 'requirements/endorsed.pdf',
            'file_type' => 'pdf',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($programHead);
        $this->postJson("/api/program-head/documents/requirements/{$requirement->id}/review", [
            'decision' => 'approved',
            'feedback' => 'Endorsed for final approval.',
        ])->assertOk();

        $approval = ApprovalRecord::where('subject_type', 'document')
            ->where('subject_id', $requirement->id)
            ->firstOrFail();

        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/approvals')
            ->assertOk()
            ->assertJsonPath('summary.pending_documents', 1)
            ->assertJsonPath('documents.0.document_name', 'Curriculum Vitae')
            ->assertJsonPath('documents.0.student.student_id', $student->student_id);

        $this->get("/api/vpaa/approvals/{$approval->id}/download")
            ->assertOk();

        $this->putJson("/api/vpaa/approvals/{$approval->id}", [
            'decision' => 'rejected',
            'remarks' => 'Please replace the unsigned document.',
        ])->assertOk()
            ->assertJsonPath('approval.status', 'rejected');

        $this->assertDatabaseHas('approval_records', [
            'id' => $approval->id,
            'status' => 'rejected',
            'decided_by' => $vpaa->id,
        ]);
        $this->assertDatabaseHas('internship_requirements', [
            'id' => $requirement->id,
            'status' => 'rejected',
            'feedback' => 'Please replace the unsigned document.',
        ]);

        $this->getJson('/api/vpaa/approvals')
            ->assertOk()
            ->assertJsonPath('summary.pending_documents', 0)
            ->assertJsonPath('history.0.status', 'rejected')
            ->assertJsonPath('history.0.decided_by', $vpaa->name);
    }

    public function test_coordinator_deployment_request_can_be_approved_by_vpaa(): void
    {
        [$student, $hte] = $this->studentContext();
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $student->college_id,
            'program_id' => $student->program_id,
        ]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);

        Sanctum::actingAs($coordinator);
        $this->putJson("/api/coordinator/htes/deployments/{$student->id}", [
            'hte_id' => $hte->id,
            'ojt_start_date' => '2026-08-01',
            'ojt_end_date' => '2026-12-15',
            'required_ojt_hours' => 486,
            'official_am_start' => '08:00',
            'official_am_end' => '12:00',
            'official_pm_start' => '13:00',
            'official_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'internship_status' => 'pending',
            'allow_past_attendance' => false,
        ])->assertOk();

        $approval = ApprovalRecord::where('subject_type', 'deployment')
            ->where('subject_id', $student->id)
            ->firstOrFail();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'schedule_status' => 'pending',
        ]);

        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/approvals')
            ->assertOk()
            ->assertJsonPath('summary.pending_deployments', 1)
            ->assertJsonPath('deployments.0.hte.name', $hte->name);

        $this->putJson("/api/vpaa/approvals/{$approval->id}", [
            'decision' => 'approved',
            'remarks' => 'Deployment schedule verified.',
        ])->assertOk()
            ->assertJsonPath('approval.status', 'approved');

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'schedule_status' => 'approved',
        ]);
        $this->assertDatabaseHas('approval_records', [
            'id' => $approval->id,
            'status' => 'approved',
            'decided_by' => $vpaa->id,
        ]);
    }

    private function studentContext(): array
    {
        $studentUser = User::factory()->create(['role' => 'student']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'BSIT',
            'is_active' => true,
        ]);
        $hte = HTE::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => 'Marinduque Technology Partner',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'HTE Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_phone' => '09170000000',
            'geofence_radius' => 100,
            'geofence_enabled' => false,
            'default_am_start' => '08:00',
            'default_am_end' => '12:00',
            'default_pm_start' => '13:00',
            'default_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-5001',
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Reyes',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'phone' => '09171111111',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_phone' => '09172222222',
            'registration_status' => 'approved',
            'internship_status' => 'pending',
        ]);

        return [$student, $hte];
    }
}
