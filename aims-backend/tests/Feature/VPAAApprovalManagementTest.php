<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\ProgramOjtSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VPAAApprovalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_student_folder_goes_from_program_head_to_dean_then_vpaa_review(): void
    {
        Storage::fake('local');
        [$student] = $this->studentContext();
        $programHead = User::factory()->create([
            'role' => 'program_head',
            'college_id' => $student->college_id,
            'program_id' => $student->program_id,
        ]);
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $student->college_id,
            'program_id' => $student->program_id,
        ]);
        \Illuminate\Support\Facades\DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $student->academic_section_id,
            'coordinator_id' => $coordinator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $student->college_id]);
        InternshipRequirement::ensureForStudent($student->id);
        $internship = $student->fresh()->currentInternship;
        $requirements = InternshipRequirement::where('student_internship_id', $internship->id)->get();
        $this->assertCount(10, $requirements);

        Sanctum::actingAs($programHead);
        $this->postJson("/api/program-head/student-internships/{$internship->id}/endorse")
            ->assertUnprocessable();

        foreach ($requirements as $requirement) {
            Sanctum::actingAs($student->user);
            $this->post("/api/student/requirements/{$requirement->id}/upload", [
                'file' => \Illuminate\Http\UploadedFile::fake()->create("requirement-{$requirement->id}.pdf", 1, 'application/pdf'),
            ])->assertOk();
            if ($requirement->id === $requirements->first()->id) {
                Sanctum::actingAs($programHead);
                $this->postJson("/api/program-head/documents/requirements/{$requirement->id}/review", ['decision' => 'approved'])
                    ->assertUnprocessable();
            }
            Sanctum::actingAs($coordinator);
            $this->postJson("/api/coordinator/students/{$student->id}/requirements/{$requirement->id}/review", ['decision' => 'approved'])
                ->assertOk();
        }

        Sanctum::actingAs($programHead);
        $this->postJson("/api/program-head/student-internships/{$internship->id}/endorse")
            ->assertUnprocessable();
        $this->getJson('/api/program-head/documents')->assertOk()
            ->assertJsonPath('requirements.0.program_head_approved', false)
            ->assertJsonPath('requirements.0.can_program_head_review', true);
        foreach ($requirements as $requirement) {
            $this->postJson("/api/program-head/documents/requirements/{$requirement->id}/review", ['decision' => 'approved'])
                ->assertOk()->assertJsonPath('requirement.status', 'approved')
                ->assertJsonPath('requirement.program_head_approved', true);
        }
        $this->getJson('/api/program-head/documents')->assertOk()
            ->assertJsonPath('requirements.0.program_head_approved', true);

        $this->postJson("/api/program-head/student-internships/{$internship->id}/endorse")
            ->assertOk()->assertJsonPath('approval.status', 'for_dean_review');
        $this->postJson("/api/program-head/student-internships/{$internship->id}/endorse")
            ->assertUnprocessable();
        $this->assertDatabaseCount('student_document_approvals', 1);
        $this->assertDatabaseMissing('approval_records', ['subject_type' => 'document', 'subject_id' => $requirements->first()->id]);

        $this->postJson("/api/program-head/documents/requirements/{$requirements->first()->id}/review", ['decision' => 'rejected', 'feedback' => 'Changed after endorsement'])
            ->assertUnprocessable();
        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/students/{$student->id}/requirements/{$requirements->first()->id}/review", ['decision' => 'rejected'])
            ->assertUnprocessable();
        Sanctum::actingAs($student->user);
        $this->post("/api/student/requirements/{$requirements->first()->id}/upload", [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('replacement.pdf', 1, 'application/pdf'),
        ])->assertUnprocessable();

        $approval = \App\Models\StudentDocumentApproval::firstOrFail();
        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/student-document-approvals')
            ->assertOk()->assertJsonPath('summary.for_review', 0);
        $this->getJson("/api/vpaa/student-document-approvals/{$approval->id}")->assertNotFound();
        $this->postJson("/api/vpaa/student-document-approvals/{$approval->id}/review")->assertNotFound();

        Sanctum::actingAs($dean->fresh());
        $this->getJson('/api/dean/documents')
            ->assertOk()->assertJsonPath('summary.for_dean_review', 1)
            ->assertJsonPath('folders.0.approved_count', 10)
            ->assertJsonPath('folders.0.required_count', 10);
        $this->getJson("/api/dean/documents/{$approval->id}")
            ->assertOk()->assertJsonCount(10, 'requirements');
        $this->get("/api/dean/documents/{$approval->id}/requirements/{$requirements->first()->id}/view")
            ->assertOk();
        $this->postJson("/api/dean/documents/{$approval->id}/approve")
            ->assertOk()->assertJsonPath('approval.status', 'for_review');
        $this->postJson("/api/dean/documents/{$approval->id}/return", ['remarks' => 'Too late'])
            ->assertUnprocessable();
        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/student-document-approvals')
            ->assertOk()->assertJsonPath('summary.for_review', 1);
        $this->postJson("/api/vpaa/student-document-approvals/{$approval->id}/review")
            ->assertOk()->assertJsonPath('approval.status', 'reviewed');
        $this->postJson("/api/vpaa/student-document-approvals/{$approval->id}/review")->assertUnprocessable();
        $this->getJson('/api/vpaa/student-document-approvals')
            ->assertOk()->assertJsonPath('summary.for_review', 0)
            ->assertJsonPath('summary.reviewed', 1)
            ->assertJsonPath('history.0.dean_decided_by', $dean->name)
            ->assertJsonPath('history.0.vpaa_reviewed_by', $vpaa->name);
    }

    public function test_deployment_approval_is_owned_by_program_head_and_dean_only_forwards(): void
    {
        [$student, $hte] = $this->studentContext();
        $coordinator = User::factory()->create(['role' => 'coordinator', 'college_id' => $student->college_id, 'program_id' => $student->program_id]);
        \Illuminate\Support\Facades\DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $student->academic_section_id, 'coordinator_id' => $coordinator->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $head = User::factory()->create(['role' => 'program_head', 'college_id' => $student->college_id, 'program_id' => $student->program_id]);
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $student->college_id]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);
        ProgramOjtSetting::create(['program_id' => $student->program_id, 'academic_period_id' => $student->academic_period_id,
            'required_hours' => 486, 'ojt_start_date' => today()->toDateString(), 'status' => 'published', 'published_at' => now()]);
        Sanctum::actingAs($coordinator);
        $this->putJson('/api/coordinator/htes/deployments/'.$student->id, [
            'hte_id' => $hte->id, 'required_ojt_hours' => 486, 'official_am_start' => '08:00', 'official_am_end' => '12:00',
            'official_pm_start' => '13:00', 'official_pm_end' => '17:00', 'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'internship_status' => 'pending', 'allow_past_attendance' => false,
        ])->assertOk();
        $approval = ApprovalRecord::where('subject_type', 'deployment')->firstOrFail();
        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/deployments')->assertOk()->assertJsonCount(0, 'deployments');
        $this->putJson('/api/vpaa/approvals/'.$approval->id, ['decision' => 'approved'])->assertForbidden();
        Sanctum::actingAs($head);
        $this->putJson('/api/program-head/deployments/'.$approval->id.'/review', ['decision' => 'approved'])->assertOk();
        $this->assertSame('active', $student->fresh()->internship_status);
        $this->assertSame($head->id, $approval->fresh()->decided_by);
        Sanctum::actingAs($dean->fresh());
        $this->getJson('/api/dean/deployments')->assertOk()->assertJsonPath('summary.pending', 1);
        $this->postJson('/api/dean/deployments/'.$approval->id.'/forward')->assertOk();
        $this->assertSame($head->id, $approval->fresh()->decided_by);
        $this->assertSame($dean->id, $approval->fresh()->dean_forwarded_by);
        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/deployments')->assertOk()->assertJsonCount(1, 'deployments');
    }

    public function test_return_and_reendorsement_reuse_one_current_folder_record(): void
    {
        Storage::fake('local');
        [$student] = $this->studentContext();
        $programHead = User::factory()->create(['role' => 'program_head', 'college_id' => $student->college_id, 'program_id' => $student->program_id]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $student->college_id]);
        InternshipRequirement::ensureForStudent($student->id);
        $internship = $student->fresh()->currentInternship;
        $requirements = InternshipRequirement::where('student_internship_id', $internship->id)->get();
        foreach ($requirements as $requirement) {
            $path = "requirements/{$requirement->id}.pdf";
            Storage::disk('local')->put($path, 'approved file');
            $requirement->update(['file_path' => $path, 'status' => 'approved', 'reviewed_by' => $programHead->id, 'reviewed_at' => now()]);
        }

        Sanctum::actingAs($programHead);
        $this->postJson("/api/program-head/student-internships/{$internship->id}/endorse")->assertOk();
        $approval = \App\Models\StudentDocumentApproval::firstOrFail();

        Sanctum::actingAs($dean->fresh());
        $this->postJson("/api/dean/documents/{$approval->id}/return", ['remarks' => 'Please recheck the folder.'])
            ->assertOk()->assertJsonPath('approval.status', 'returned');
        $this->getJson('/api/dean/documents')
            ->assertJsonPath('summary.returned', 1)
            ->assertJsonPath('summary.for_dean_review', 0);

        Sanctum::actingAs($programHead);
        $this->postJson("/api/program-head/student-internships/{$internship->id}/re-endorse")
            ->assertOk()->assertJsonPath('approval.status', 'for_dean_review');
        $this->assertDatabaseCount('student_document_approvals', 1);
        $this->assertDatabaseHas('student_document_approvals', ['id' => $approval->id, 'decided_by' => null, 'decided_at' => null]);

        $requirements->last()->update(['status' => 'rejected']);
        Sanctum::actingAs($dean->fresh());
        $this->postJson("/api/dean/documents/{$approval->id}/approve")
            ->assertUnprocessable();
        $this->getJson("/api/dean/documents/{$approval->id}")
            ->assertOk()->assertJsonCount(10, 'requirements');

        Storage::disk('local')->delete("requirements/{$requirements->first()->id}.pdf");
        $requirements->last()->update(['status' => 'approved']);
        $this->postJson("/api/dean/documents/{$approval->id}/approve")
            ->assertUnprocessable();
        $this->getJson("/api/dean/documents/{$approval->id}")
            ->assertOk()->assertJsonPath('requirements.0.file_available', false);
        $this->get("/api/dean/documents/{$approval->id}/requirements/{$requirements->first()->id}/view")
            ->assertNotFound();
    }

    public function test_only_authorized_roles_can_endorse_or_decide_folders(): void
    {
        [$student] = $this->studentContext();
        $internship = $student->fresh()->currentInternship;
        $studentUser = $student->user;
        $programHead = User::factory()->create(['role' => 'program_head', 'college_id' => $student->college_id, 'program_id' => $student->program_id]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);
        $approval = \App\Models\StudentDocumentApproval::create([
            'student_internship_id' => $internship->id,
            'endorsed_by' => $programHead->id,
            'endorsed_at' => now(),
            'status' => 'for_dean_review',
        ]);

        Sanctum::actingAs($studentUser);
        $this->postJson("/api/program-head/student-internships/{$internship->id}/endorse")->assertForbidden();
        $this->getJson('/api/vpaa/student-document-approvals')->assertForbidden();

        foreach (['coordinator', 'supervisor'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/vpaa/student-document-approvals')->assertForbidden();
            $this->postJson("/api/vpaa/student-document-approvals/{$approval->id}/review")->assertForbidden();
        }

        Sanctum::actingAs($programHead);
        $this->getJson('/api/vpaa/student-document-approvals')->assertForbidden();
        $this->postJson("/api/program-head/student-internships/{$internship->id}/endorse")->assertUnprocessable();

        Sanctum::actingAs($vpaa);
        $this->postJson("/api/program-head/student-internships/{$internship->id}/endorse")->assertForbidden();
        $this->getJson("/api/vpaa/student-document-approvals/{$approval->id}")->assertNotFound();

        $otherCollege = College::create(['name' => 'Other College', 'code' => 'OTH', 'is_active' => true]);
        $otherProgram = Program::create(['college_id' => $otherCollege->id, 'name' => 'Other Program', 'code' => 'OTHER', 'is_active' => true]);
        $otherHead = User::factory()->create(['role' => 'program_head', 'college_id' => $otherCollege->id, 'program_id' => $otherProgram->id]);
        Sanctum::actingAs($otherHead);
        $this->postJson("/api/program-head/student-internships/{$internship->id}/endorse")->assertNotFound();

        $otherDean = User::factory()->create(['role' => 'dean', 'college_id' => $otherCollege->id]);
        Sanctum::actingAs($otherDean->fresh());
        $this->getJson('/api/dean/documents')->assertOk()->assertJsonPath('summary.for_dean_review', 0);
        $this->getJson("/api/dean/documents/{$approval->id}")->assertNotFound();
        $this->postJson("/api/dean/documents/{$approval->id}/approve")->assertNotFound();
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
            'name' => 'Marinduque Technology Partner',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'HTE Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_number' => '09170000000',
            'geofence_radius' => 100,
            'geofence_enabled' => false,
            'default_am_start' => '08:00',
            'default_am_end' => '12:00',
            'default_pm_start' => '13:00',
            'default_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
        ]);
        $hte->programs()->attach($program->id, ['is_active' => true]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-5001',
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Reyes',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'contact_number' => '09171111111',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_period_id' => $this->academicPeriod()->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09172222222',
            'registration_status' => 'approved',
            'internship_status' => 'pending',
        ]);

        return [$student, $hte];
    }
}
