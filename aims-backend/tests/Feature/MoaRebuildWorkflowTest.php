<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\College;
use App\Models\HTE;
use App\Models\MOA;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoaRebuildWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dean_cannot_view_or_decide_another_colleges_moa(): void
    {
        [$hte, $programs, , $heads] = $this->context();
        $otherCollege = College::create(['name' => 'Education', 'code' => 'CED', 'is_active' => true]);
        $otherProgram = Program::create(['college_id' => $otherCollege->id, 'name' => 'Education', 'code' => 'BSED', 'is_active' => true]);
        $otherHead = User::factory()->create(['role' => 'program_head', 'college_id' => $otherCollege->id, 'program_id' => $otherProgram->id]);
        $ownMoa = MOA::create(['hte_id' => $hte->id, 'program_id' => $programs[0]->id,
            'file_path' => 'moas/own.pdf', 'effective_date' => today(), 'expiration_date' => today()->addYear(),
            'status' => 'pending', 'program_status' => 'endorsed', 'dean_endorsed_at' => now()]);
        $otherMoa = MOA::create(['hte_id' => $hte->id, 'program_id' => $otherProgram->id,
            'file_path' => 'moas/other.pdf', 'effective_date' => today(), 'expiration_date' => today()->addYear(),
            'status' => 'pending', 'program_status' => 'endorsed']);
        ApprovalRecord::submit('moa', $ownMoa->id, $heads[0]->id);
        $otherApproval = ApprovalRecord::submit('moa', $otherMoa->id, $otherHead->id);
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $programs[0]->college_id]);
        Sanctum::actingAs($dean->fresh());

        $this->getJson('/api/dean/moas')->assertOk()->assertJsonCount(1, 'pending')
            ->assertJsonPath('pending.0.program_id', $programs[0]->id);
        $this->putJson("/api/dean/moas/{$otherApproval->id}", ['decision' => 'approved'])->assertNotFound();
        $this->get("/api/dean/moas/{$otherApproval->id}/download")->assertNotFound();
        $this->assertDatabaseHas('moas', ['id' => $otherMoa->id, 'status' => 'pending']);
    }

    public function test_separate_program_moas_receive_dean_final_approval_then_vpaa_review(): void
    {
        Storage::fake('local');
        [$hte, $programs, $coordinators, $heads, $vpaa] = $this->context();
        $this->assertFalse(Schema::hasColumn('moas', 'college_id'));
        $this->assertDatabaseCount('coordinator_section_assignments', 0);

        $ids = [];
        foreach ($programs as $index => $program) {
            Sanctum::actingAs($coordinators[$index]);
            $ids[] = $this->post('/api/coordinator/htes/moas', [
                'hte_id' => $hte->id,
                'program_id' => $program->id,
                'effective_date' => today()->subDay()->toDateString(),
                'expiration_date' => today()->addYear()->toDateString(),
                'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated()
                ->assertJsonPath('moa.program_id', $program->id)
                ->assertJsonPath('moa.college.id', $program->college_id)
                ->json('moa.id');
        }
        $this->assertDatabaseCount('moas', 2);
        $this->assertDatabaseCount('coordinator_section_assignments', 0);

        Sanctum::actingAs($coordinators[0]);
        $this->getJson('/api/coordinator/htes')->assertOk()
            ->assertJsonPath('programs.0.id', $programs[0]->id)
            ->assertJsonPath('moas.0.program.code', $programs[0]->code)
            ->assertJsonPath('moas.0.college.code', $programs[0]->college->code);
        $this->get("/api/coordinator/htes/moas/{$ids[1]}/download")->assertNotFound();
        Sanctum::actingAs($heads[0]);
        $this->get("/api/program-head/documents/moas/{$ids[1]}/download")->assertNotFound();
        $this->postJson("/api/program-head/documents/moas/{$ids[1]}/review", ['decision' => 'endorsed'])->assertNotFound();

        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/moas')->assertOk()->assertJsonCount(0, 'pending');

        Sanctum::actingAs($heads[0]);
        $this->postJson("/api/program-head/documents/moas/{$ids[0]}/review", ['decision' => 'endorsed'])
            ->assertOk()->assertJsonPath('moa.program_status', 'endorsed')
            ->assertJsonPath('moa.status', 'pending');
        $this->getJson('/api/program-head/documents')->assertOk()
            ->assertJsonPath('moas.0.reviewer', $heads[0]->name)
            ->assertJsonPath('moas.0.program', $programs[0]->code);
        $this->postJson("/api/program-head/documents/moas/{$ids[0]}/review", ['decision' => 'endorsed'])
            ->assertUnprocessable();

        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/moas')->assertOk()->assertJsonCount(0, 'pending');
        $approval = ApprovalRecord::where('subject_type', 'moa')->where('subject_id', $ids[0])->firstOrFail();
        $this->get("/api/vpaa/moas/{$approval->id}/download")->assertNotFound();
        $this->postJson("/api/vpaa/moas/{$approval->id}/review")->assertUnprocessable();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $programs[0]->college_id]);
        $this->assertSame((int) $programs[0]->college_id, $dean->fresh()->normalizedCollegeId());
        Sanctum::actingAs($dean->fresh());
        $this->getJson('/api/dean/moas')->assertOk()->assertJsonCount(1, 'pending');
        $this->putJson("/api/dean/moas/{$approval->id}", ['decision' => 'endorsed'])->assertUnprocessable();
        $this->putJson("/api/dean/moas/{$approval->id}", ['decision' => 'approved'])->assertOk();
        $this->assertDatabaseHas('moas', ['id' => $ids[0], 'status' => 'approved', 'dean_endorsed_by' => $dean->id, 'approved_by' => $dean->id]);
        $this->getJson('/api/dean/moas')->assertOk()->assertJsonCount(0, 'pending');
        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/moas')->assertOk()->assertJsonCount(1, 'pending')
            ->assertJsonPath('pending.0.program_id', $programs[0]->id)
            ->assertJsonPath('pending.0.program', $programs[0]->code)
            ->assertJsonPath('pending.0.college_code', $programs[0]->college->code)
            ->assertJsonPath('pending.0.dean_approved_by', $dean->name);
        $this->get("/api/vpaa/moas/{$approval->id}/download")->assertOk();
        $this->postJson("/api/vpaa/moas/{$approval->id}/review")->assertOk();
        $this->postJson("/api/vpaa/moas/{$approval->id}/review")->assertUnprocessable();
        $this->assertDatabaseHas('moas', ['id' => $ids[0], 'status' => 'approved', 'approved_by' => $dean->id, 'vpaa_reviewed_by' => $vpaa->id]);
        $this->getJson('/api/vpaa/moas')->assertOk()->assertJsonCount(0, 'pending')->assertJsonPath('summary.reviewed', 1);
        $this->assertDatabaseHas('moas', ['id' => $ids[1], 'status' => 'pending', 'program_status' => 'pending']);
        $this->assertDatabaseCount('coordinator_section_assignments', 0);
    }

    public function test_duplicate_and_cross_program_uploads_are_rejected_but_expired_moa_can_be_renewed(): void
    {
        Storage::fake('local');
        [$hte, $programs, $coordinators] = $this->context();
        Sanctum::actingAs($coordinators[0]);
        $payload = [
            'hte_id' => $hte->id,
            'program_id' => $programs[0]->id,
            'effective_date' => today()->subMonth()->toDateString(),
            'expiration_date' => today()->addMonth()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ];
        $this->post('/api/coordinator/htes/moas', $payload, ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/coordinator/htes/moas', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('hte_id');
        $this->post('/api/coordinator/htes/moas', [...$payload, 'program_id' => $programs[1]->id], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('program_id');
        MOA::firstOrFail()->update(['expiration_date' => today()->subDay()]);
        $this->post('/api/coordinator/htes/moas', $payload, ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_expired_moa_is_viewable_but_cannot_be_endorsed_or_finally_approved(): void
    {
        Storage::fake('local');
        [$hte, $programs, $coordinators, $heads, $vpaa] = $this->context();
        Sanctum::actingAs($coordinators[0]);
        $id = $this->post('/api/coordinator/htes/moas', [
            'hte_id' => $hte->id,
            'program_id' => $programs[0]->id,
            'effective_date' => today()->subYear()->toDateString(),
            'expiration_date' => today()->subDay()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('moa.id');

        Sanctum::actingAs($heads[0]);
        $this->getJson('/api/program-head/documents')->assertOk()
            ->assertJsonPath('moas.0.validity', 'expired');
        $this->get("/api/program-head/documents/moas/{$id}/download")->assertOk();
        $this->postJson("/api/program-head/documents/moas/{$id}/review", ['decision' => 'endorsed'])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('approval_records', ['subject_type' => 'moa', 'subject_id' => $id]);

        MOA::findOrFail($id)->update(['program_status' => 'endorsed', 'program_reviewed_by' => $heads[0]->id, 'program_reviewed_at' => now()]);
        $approval = ApprovalRecord::submit('moa', $id, $heads[0]->id);
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $programs[0]->college_id]);
        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/moas/{$approval->id}", ['decision' => 'approved'])->assertUnprocessable();
        $this->assertDatabaseHas('moas', ['id' => $id, 'status' => 'pending']);
    }

    public function test_program_head_can_return_and_dean_requires_remarks_for_return(): void
    {
        Storage::fake('local');
        [$hte, $programs, $coordinators, $heads, $vpaa] = $this->context();
        Sanctum::actingAs($coordinators[0]);
        $upload = fn () => $this->post('/api/coordinator/htes/moas', [
            'hte_id' => $hte->id,
            'program_id' => $programs[0]->id,
            'effective_date' => today()->toDateString(),
            'expiration_date' => today()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('moa.id');
        $firstId = $upload();

        Sanctum::actingAs($heads[0]);
        $this->postJson("/api/program-head/documents/moas/{$firstId}/review", ['decision' => 'returned'])->assertUnprocessable();
        $this->postJson("/api/program-head/documents/moas/{$firstId}/review", ['decision' => 'returned', 'feedback' => 'Missing signature.'])
            ->assertOk()->assertJsonPath('moa.program_status', 'returned');
        $this->assertDatabaseHas('moas', ['id' => $firstId, 'status' => 'pending', 'program_status' => 'returned']);
        Sanctum::actingAs($coordinators[0]);
        $this->getJson('/api/coordinator/htes')->assertOk()->assertJsonPath('moas.0.program_feedback', 'Missing signature.');

        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/moas')->assertOk()->assertJsonCount(0, 'pending');

        Sanctum::actingAs($coordinators[0]);
        $secondId = $upload();
        Sanctum::actingAs($heads[0]);
        $this->postJson("/api/program-head/documents/moas/{$secondId}/review", ['decision' => 'endorsed'])->assertOk();
        $approval = ApprovalRecord::where('subject_type', 'moa')->where('subject_id', $secondId)->firstOrFail();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $programs[0]->college_id]);
        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/moas/{$approval->id}", ['decision' => 'returned'])->assertUnprocessable();
        $this->putJson("/api/dean/moas/{$approval->id}", ['decision' => 'returned', 'remarks' => 'Please revise the agreement.'])->assertOk();
        $this->assertDatabaseHas('moas', ['id' => $secondId, 'status' => 'returned', 'approved_by' => null]);
        $this->getJson('/api/dean/moas')->assertOk()->assertJsonCount(0, 'pending')
            ->assertJsonPath('history.0.approval_status', 'returned');
    }

    public function test_vpaa_can_only_acknowledge_a_dean_approved_moa(): void
    {
        Storage::fake('local');
        [$hte, $programs, $coordinators, $heads, $vpaa] = $this->context();
        Sanctum::actingAs($coordinators[0]);
        $moaId = $this->post('/api/coordinator/htes/moas', [
            'hte_id' => $hte->id,
            'program_id' => $programs[0]->id,
            'effective_date' => today()->toDateString(),
            'expiration_date' => today()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('moa.id');
        Sanctum::actingAs($heads[0]);
        $this->postJson("/api/program-head/documents/moas/{$moaId}/review", ['decision' => 'endorsed'])->assertOk();
        $approval = ApprovalRecord::where('subject_type', 'moa')->where('subject_id', $moaId)->firstOrFail();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $programs[0]->college_id]);
        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/moas/{$approval->id}", ['decision' => 'approved'])->assertOk();
        Sanctum::actingAs($vpaa);
        $this->putJson("/api/vpaa/moas/{$approval->id}", ['decision' => 'returned'])->assertNotFound();
        $this->postJson("/api/vpaa/moas/{$approval->id}/review")->assertOk();
        $this->assertDatabaseHas('moas', ['id' => $moaId, 'status' => 'approved', 'approved_by' => $dean->id, 'vpaa_reviewed_by' => $vpaa->id]);
        $this->getJson('/api/vpaa/moas')->assertOk()->assertJsonCount(0, 'pending')
            ->assertJsonPath('history.0.vpaa_reviewed_by', $vpaa->name);
        Sanctum::actingAs($heads[0]);
        $this->getJson('/api/program-head/documents')->assertOk()
            ->assertJsonPath('moas.0.status', 'approved');
        Sanctum::actingAs($coordinators[0]);
        $this->getJson('/api/coordinator/htes')->assertOk()
            ->assertJsonPath('moas.0.status', 'approved');
    }

    public function test_coordinator_can_replace_and_resubmit_dean_returned_moa_for_a_new_review(): void
    {
        Storage::fake('local');
        [$hte, $programs, $coordinators, $heads, $vpaa] = $this->context();
        Sanctum::actingAs($coordinators[0]);
        $moaId = $this->post('/api/coordinator/htes/moas', [
            'hte_id' => $hte->id,
            'program_id' => $programs[0]->id,
            'effective_date' => today()->toDateString(),
            'expiration_date' => today()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('original.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('moa.id');
        $originalPath = MOA::findOrFail($moaId)->file_path;
        Sanctum::actingAs($heads[0]);
        $this->postJson("/api/program-head/documents/moas/{$moaId}/review", ['decision' => 'endorsed'])->assertOk();
        $oldApproval = ApprovalRecord::where('subject_type', 'moa')->where('subject_id', $moaId)->firstOrFail();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $programs[0]->college_id]);
        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/moas/{$oldApproval->id}", ['decision' => 'returned', 'remarks' => 'Revise the agreement.'])->assertOk();

        Sanctum::actingAs($coordinators[0]);
        $this->post("/api/coordinator/htes/moas/{$moaId}", [
            '_method' => 'PUT',
            'effective_date' => today()->toDateString(),
            'expiration_date' => today()->addYears(2)->toDateString(),
            'file' => UploadedFile::fake()->create('revised.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('moa.program_status', 'pending')
            ->assertJsonPath('moa.status', 'pending');
        $revised = MOA::findOrFail($moaId);
        $this->assertNotSame($originalPath, $revised->file_path);
        Storage::disk('local')->assertExists($revised->file_path);
        $this->assertNull($revised->dean_endorsed_at);
        $this->getJson('/api/coordinator/htes')->assertOk()->assertJsonPath('moas.0.remarks', null);

        Sanctum::actingAs($heads[0]);
        $this->getJson('/api/program-head/documents')->assertOk()->assertJsonPath('moas.0.program_status', 'pending');
        $this->postJson("/api/program-head/documents/moas/{$moaId}/review", ['decision' => 'endorsed'])->assertOk();
        $newApproval = ApprovalRecord::where('subject_type', 'moa')->where('subject_id', $moaId)->where('status', 'pending')->firstOrFail();
        $this->assertNotSame($oldApproval->id, $newApproval->id);
        Sanctum::actingAs($dean->fresh());
        $this->getJson('/api/dean/moas')->assertOk()->assertJsonCount(1, 'pending');
        $this->putJson("/api/dean/moas/{$newApproval->id}", ['decision' => 'approved'])->assertOk();
        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/moas')->assertOk()->assertJsonCount(1, 'pending');
        $this->postJson("/api/vpaa/moas/{$oldApproval->id}/review")->assertUnprocessable();
        $this->postJson("/api/vpaa/moas/{$newApproval->id}/review")->assertOk();
        $this->assertDatabaseHas('moas', ['id' => $moaId, 'status' => 'approved', 'approved_by' => $dean->id, 'vpaa_reviewed_by' => $vpaa->id]);
    }

    public function test_program_head_returns_reviewed_moa_before_coordinator_can_revise_or_delete(): void
    {
        Storage::fake('local');
        [$hte, $programs, $coordinators, $heads, $vpaa] = $this->context();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $programs[0]->college_id]);

        Sanctum::actingAs($coordinators[0]);
        $moaId = $this->post('/api/coordinator/htes/moas', [
            'hte_id' => $hte->id,
            'program_id' => $programs[0]->id,
            'effective_date' => today()->toDateString(),
            'expiration_date' => today()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('moa.id');

        Sanctum::actingAs($heads[0]);
        $this->postJson("/api/program-head/documents/moas/{$moaId}/review", ['decision' => 'endorsed'])->assertOk();
        $approval = ApprovalRecord::where('subject_type', 'moa')->where('subject_id', $moaId)->firstOrFail();

        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/moas/{$approval->id}", ['decision' => 'approved'])->assertOk();

        Sanctum::actingAs($coordinators[0]);
        $dates = [
            'effective_date' => today()->toDateString(),
            'expiration_date' => today()->addYears(2)->toDateString(),
        ];
        $this->putJson("/api/coordinator/htes/moas/{$moaId}", $dates)->assertUnprocessable();
        $this->deleteJson("/api/coordinator/htes/moas/{$moaId}")->assertUnprocessable();

        Sanctum::actingAs($vpaa);
        $this->postJson("/api/vpaa/moas/{$approval->id}/review")->assertOk();

        Sanctum::actingAs($coordinators[0]);
        $this->putJson("/api/coordinator/htes/moas/{$moaId}", $dates)->assertUnprocessable();
        $this->deleteJson("/api/coordinator/htes/moas/{$moaId}")->assertUnprocessable();

        Sanctum::actingAs($heads[0]);
        $this->postJson("/api/program-head/documents/moas/{$moaId}/review", ['decision' => 'endorsed'])->assertUnprocessable();
        $this->postJson("/api/program-head/documents/moas/{$moaId}/review", ['decision' => 'returned'])->assertUnprocessable();
        $this->postJson("/api/program-head/documents/moas/{$moaId}/review", [
            'decision' => 'returned', 'feedback' => 'Incorrect PDF content.',
        ])->assertOk()->assertJsonPath('moa.program_status', 'returned')
            ->assertJsonPath('moa.program_feedback', 'Incorrect PDF content.')
            ->assertJsonPath('moa.vpaa_reviewed_at', null);

        Sanctum::actingAs($coordinators[0]);
        $this->putJson("/api/coordinator/htes/moas/{$moaId}", $dates)->assertOk()
            ->assertJsonPath('moa.status', 'pending')
            ->assertJsonPath('moa.program_status', 'pending')
            ->assertJsonPath('moa.vpaa_reviewed_at', null)
            ->assertJsonPath('moa.approved_at', null);
        $this->assertDatabaseHas('moas', ['id' => $moaId, 'deleted_at' => null]);

        Sanctum::actingAs($heads[0]);
        $this->postJson("/api/program-head/documents/moas/{$moaId}/review", ['decision' => 'endorsed'])->assertOk();
        $newApproval = ApprovalRecord::where('subject_type', 'moa')->where('subject_id', $moaId)
            ->orderByDesc('id')->firstOrFail();
        $this->assertNotSame($approval->id, $newApproval->id);
        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/moas/{$newApproval->id}", ['decision' => 'approved'])->assertOk();
        Sanctum::actingAs($vpaa);
        $this->postJson("/api/vpaa/moas/{$newApproval->id}/review")->assertOk();

        Sanctum::actingAs($heads[0]);
        $this->postJson("/api/program-head/documents/moas/{$moaId}/review", [
            'decision' => 'returned', 'feedback' => 'Withdraw this agreement.',
        ])->assertOk();
        Sanctum::actingAs($coordinators[0]);
        $this->deleteJson("/api/coordinator/htes/moas/{$moaId}")->assertOk();
        $this->assertSoftDeleted('moas', ['id' => $moaId]);
        $this->getJson('/api/coordinator/htes')->assertOk()->assertJsonCount(0, 'moas');
    }

    private function context(): array
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $programs = [
            Program::create(['college_id' => $college->id, 'name' => 'Information Systems', 'code' => 'BSIS', 'is_active' => true]),
            Program::create(['college_id' => $college->id, 'name' => 'Tourism', 'code' => 'BSTM', 'is_active' => true]),
        ];
        $coordinators = [];
        $heads = [];
        foreach ($programs as $program) {
            $coordinators[] = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
            $heads[] = User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id]);
        }
        $hte = HTE::create([
            'name' => 'Marinduque State University', 'address' => 'Santa Cruz',
            'contact_person' => 'Supervisor', 'contact_email' => 'supervisor@example.com',
            'contact_number' => '09170000000', 'geofence_radius' => 100,
            'geofence_enabled' => false, 'is_active' => true,
        ]);
        foreach ($programs as $index => $program) {
            DB::table('hte_programs')->insert([
                'hte_id' => $hte->id, 'program_id' => $program->id,
                'linked_by' => $coordinators[$index]->id, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$hte, $programs, $coordinators, $heads, User::factory()->create(['role' => 'vpaa'])];
    }
}
