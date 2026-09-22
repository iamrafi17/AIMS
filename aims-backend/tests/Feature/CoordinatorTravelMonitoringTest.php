<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\HTE;
use App\Models\Program;
use App\Models\Student;
use App\Models\TravelLog;
use App\Models\TravelReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorTravelMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_controls_session_and_report_completes_the_full_validation_chain(): void
    {
        Http::fake();
        Storage::fake('local');
        [$studentUser, $student, $coordinator, $programHead, $vpaa, $hte] = $this->context();

        Sanctum::actingAs($studentUser);
        $sessionId = $this->postJson('/api/student/travel/start', $this->coordinates())
            ->assertCreated()
            ->assertJsonPath('session.status', 'active')
            ->assertJsonPath('session.student.id', $student->id)
            ->assertJsonPath('session.coordinator.id', $coordinator->id)
            ->assertJsonPath('session.hte.id', $hte->id)
            ->assertJsonPath('session.points.0.event_type', 'start')
            ->json('session.id');

        $this->postJson("/api/student/travel/{$sessionId}/departure", $this->coordinates())
            ->assertOk()->assertJsonPath('session.status', 'traveling');
        $this->postJson("/api/student/travel/{$sessionId}/checkpoints", [
            ...$this->coordinates(13.48, 122.03),
            'remarks' => 'Traffic checkpoint',
        ])->assertCreated()->assertJsonPath('session.points.2.event_type', 'checkpoint');
        $this->postJson("/api/student/travel/{$sessionId}/arrival", $this->coordinates(13.49, 122.04))
            ->assertOk()->assertJsonPath('session.status', 'arrived');
        $this->postJson("/api/student/travel/{$sessionId}/end")
            ->assertUnprocessable()->assertJsonPath('message', 'Upload the arrival photo before ending the session.');
        $this->post("/api/student/travel/{$sessionId}/arrival-photo", [
            'photo' => $this->image(),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('session.has_arrival_photo', true);
        $this->postJson("/api/student/travel/{$sessionId}/end")
            ->assertOk()->assertJsonPath('session.status', 'completed');

        $travel = TravelLog::findOrFail($sessionId);
        $this->assertSame($studentUser->id, $travel->created_by);
        $this->assertSame($coordinator->id, $travel->coordinator_id);
        $this->assertDatabaseCount('travel_checkpoints', 4);
        Storage::disk('local')->assertExists($travel->arrival_photo_path);

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinator/travel')
            ->assertOk()->assertJsonPath('sessions.0.id', $sessionId)->assertJsonPath('summary.completed_sessions', 1);
        $reportId = $this->postJson("/api/coordinator/travel/{$sessionId}/reports")
            ->assertCreated()->assertJsonPath('session.report.status', 'draft')->json('session.report.id');
        $this->putJson("/api/coordinator/travel/reports/{$reportId}", ['remarks' => 'Route and evidence reviewed.'])
            ->assertOk()->assertJsonPath('report.remarks', 'Route and evidence reviewed.');
        $this->postJson("/api/coordinator/travel/reports/{$reportId}/submit")
            ->assertOk()->assertJsonPath('report.status', 'pending_program_head');

        Sanctum::actingAs($programHead);
        $this->getJson('/api/program-head/travel')
            ->assertOk()->assertJsonPath('sessions.0.report.status', 'pending_program_head');
        $this->putJson("/api/program-head/travel/reports/{$reportId}/review", [
            'comments' => 'Evidence is complete.',
        ])->assertOk()->assertJsonPath('report.status', 'pending_vpaa');
        $this->postJson("/api/program-head/travel/reports/{$reportId}/forward")->assertNotFound();

        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/travel')
            ->assertOk()->assertJsonPath('sessions.0.report.status', 'pending_vpaa');
        $this->putJson("/api/vpaa/travel/reports/{$reportId}/review", [
            'comments' => 'Final validation complete.',
        ])->assertOk()->assertJsonPath('report.status', 'final_validated');

        Sanctum::actingAs($studentUser);
        $this->getJson('/api/student/travel')
            ->assertOk()->assertJsonPath('history.0.report.status', 'final_validated');
        $this->assertDatabaseHas('travel_reports', ['id' => $reportId, 'status' => 'final_validated', 'version' => 1]);
        $this->assertDatabaseCount('travel_report_validations', 3);
        $this->assertDatabaseHas('travel_report_validations', [
            'travel_report_id' => $reportId,
            'reviewer_role' => 'program_head',
            'action' => 'validated_and_forwarded',
        ]);
        $this->assertDatabaseHas('system_notifications', ['user_id' => $vpaa->id, 'title' => 'Travel report ready for final validation']);
        $this->assertDatabaseHas('system_notifications', ['user_id' => $studentUser->id, 'title' => 'Travel report final validated']);
    }

    public function test_validate_only_workflow_rejects_revision_actions_and_locks_submitted_reports(): void
    {
        Http::fake();
        Storage::fake('local');
        [$studentUser, , $coordinator, $programHead, $vpaa] = $this->context();
        $sessionId = $this->completeSession($studentUser);

        Sanctum::actingAs($coordinator);
        $reportId = $this->postJson("/api/coordinator/travel/{$sessionId}/reports")->assertCreated()->json('session.report.id');
        $this->postJson("/api/coordinator/travel/reports/{$reportId}/submit")->assertOk();

        Sanctum::actingAs($programHead);
        $this->putJson("/api/program-head/travel/reports/{$reportId}/review", [
            'decision' => 'return', 'reason' => 'Clarify the route', 'comments' => 'Add checkpoint context.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['decision', 'reason']);
        $this->assertDatabaseHas('travel_reports', ['id' => $reportId, 'status' => 'pending_program_head']);
        $this->putJson("/api/program-head/travel/reports/{$reportId}/review", [
            'comments' => 'Evidence validated.',
        ])->assertOk()->assertJsonPath('report.status', 'pending_vpaa');
        $this->postJson("/api/program-head/travel/reports/{$reportId}/forward")->assertNotFound();

        Sanctum::actingAs($coordinator);
        $this->putJson("/api/coordinator/travel/reports/{$reportId}", ['remarks' => 'Late change.'])
            ->assertUnprocessable()->assertJsonPath('message', 'This report is locked after submission.');
        $this->postJson("/api/coordinator/travel/reports/{$reportId}/submit")
            ->assertUnprocessable()->assertJsonPath('message', 'Only a draft report can be submitted.');

        Sanctum::actingAs($vpaa);
        $this->putJson("/api/vpaa/travel/reports/{$reportId}/review", [
            'decision' => 'return', 'reason' => 'Need stronger narrative', 'comments' => 'Revise the official remarks.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['decision', 'reason']);
        $this->assertDatabaseHas('travel_reports', ['id' => $reportId, 'status' => 'pending_vpaa']);
        $this->putJson("/api/vpaa/travel/reports/{$reportId}/review", ['comments' => 'Final validation complete.'])
            ->assertOk()->assertJsonPath('report.status', 'final_validated');

        $this->assertSame(1, TravelReport::findOrFail($reportId)->version);
        $this->assertDatabaseMissing('travel_report_validations', ['travel_report_id' => $reportId, 'action' => 'returned']);
        $this->assertDatabaseHas('travel_report_validations', ['travel_report_id' => $reportId, 'action' => 'final_validated', 'report_version' => 1]);
    }

    public function test_invalid_transitions_and_cross_account_changes_are_rejected(): void
    {
        Http::fake();
        Storage::fake('local');
        [$studentUser, , $coordinator, $programHead, $vpaa] = $this->context();
        Sanctum::actingAs($studentUser);
        $sessionId = $this->postJson('/api/student/travel/start', $this->coordinates())->assertCreated()->json('session.id');
        $this->postJson("/api/student/travel/{$sessionId}/checkpoints", $this->coordinates())->assertUnprocessable();
        $this->postJson('/api/student/travel/start', $this->coordinates())->assertUnprocessable()->assertJsonValidationErrors('session');

        $otherStudent = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($otherStudent);
        $this->postJson("/api/student/travel/{$sessionId}/departure", $this->coordinates())->assertNotFound();

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/travel/{$sessionId}/reports")
            ->assertUnprocessable()->assertJsonPath('message', 'A report can only be generated after the Student completes the travel session.');
        $this->postJson('/api/coordinator/travel/start', $this->coordinates())->assertStatus(405);

        Sanctum::actingAs($studentUser);
        $this->postJson("/api/student/travel/{$sessionId}/departure", $this->coordinates())->assertOk();
        $this->postJson("/api/student/travel/{$sessionId}/checkpoints", $this->coordinates(13.0, 121.0))
            ->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->postJson("/api/student/travel/{$sessionId}/arrival", $this->coordinates())->assertOk();
        $this->post("/api/student/travel/{$sessionId}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->postJson("/api/student/travel/{$sessionId}/end")->assertOk();
        Sanctum::actingAs($coordinator);
        $reportId = $this->postJson("/api/coordinator/travel/{$sessionId}/reports")->assertCreated()->json('session.report.id');
        $this->postJson("/api/coordinator/travel/reports/{$reportId}/submit")->assertOk();

        Sanctum::actingAs($vpaa);
        $this->putJson("/api/vpaa/travel/reports/{$reportId}/review")->assertUnprocessable();

        $otherProgramHead = User::factory()->create(['role' => 'program_head']);
        Sanctum::actingAs($otherProgramHead);
        $this->putJson("/api/program-head/travel/reports/{$reportId}/review")->assertForbidden();

        Sanctum::actingAs($programHead);
        $this->putJson("/api/program-head/travel/reports/{$reportId}/review", ['decision' => 'return'])
            ->assertUnprocessable()->assertJsonValidationErrors('decision');
    }

    public function test_travel_accepts_supported_accuracy_and_rejects_inaccurate_and_stale_gps_readings(): void
    {
        Http::fake();
        [$studentUser] = $this->context();
        Sanctum::actingAs($studentUser);

        $this->postJson('/api/student/travel/start', [
            ...$this->coordinates(),
            'accuracy' => 750,
        ])->assertUnprocessable()->assertJsonValidationErrors('accuracy');

        $this->postJson('/api/student/travel/start', [
            ...$this->coordinates(),
            'position_timestamp' => now()->subMinutes(5)->getTimestampMs(),
        ])->assertUnprocessable()->assertJsonValidationErrors('location');

        $this->postJson('/api/student/travel/start', [
            ...$this->coordinates(),
            'accuracy' => 500,
        ])->assertCreated()->assertJsonPath('session.status', 'active');
    }

    private function completeSession(User $studentUser): int
    {
        Sanctum::actingAs($studentUser);
        $id = $this->postJson('/api/student/travel/start', $this->coordinates())->assertCreated()->json('session.id');
        $this->postJson("/api/student/travel/{$id}/departure", $this->coordinates())->assertOk();
        $this->postJson("/api/student/travel/{$id}/checkpoint", $this->coordinates())->assertNotFound();
        $this->postJson("/api/student/travel/{$id}/checkpoints", $this->coordinates())->assertCreated();
        $this->postJson("/api/student/travel/{$id}/arrival", $this->coordinates())->assertOk();
        $this->post("/api/student/travel/{$id}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->postJson("/api/student/travel/{$id}/end")->assertOk();

        return $id;
    }

    private function context(): array
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        $programHead = User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);
        $hte = HTE::create([
            'name' => 'Partner Company', 'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor', 'contact_email' => 'supervisor@example.com', 'contact_number' => '09170000000', 'latitude' => 13.47, 'longitude' => 122.02, 'is_active' => true,
        ]);
        $hte->programs()->attach($program->id, [
            'linked_by' => $coordinator->id,
            'is_active' => true,
        ]);
        $studentUser = User::factory()->create(['role' => 'student']);
        $period = $this->academicPeriod();
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_id' => '2026-4001', 'first_name' => 'Ada', 'last_name' => 'Intern', 'gender' => 'female', 'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque', 'contact_number' => '09171111111', 'college_id' => $college->id, 'program_id' => $program->id, 'academic_period_id' => $period->id, 'year_level' => 4, 'section' => 'A',
            'parent_name' => 'Test Parent', 'parent_address' => 'Santa Cruz, Marinduque', 'parent_contact_number' => '09172222222', 'hte_id' => $hte->id,
            'registration_status' => 'approved', 'internship_status' => 'active', 'ojt_start_date' => now()->subDay()->toDateString(), 'ojt_end_date' => now()->addMonth()->toDateString(),
        ]);

        return [$studentUser, $student, $coordinator, $programHead, $vpaa, $hte, $college, $program];
    }

    private function coordinates(float $latitude = 13.475, float $longitude = 122.027): array
    {
        return ['latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => 8.5, 'position_timestamp' => now()->getTimestampMs()];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('arrival.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nCEAAAAASUVORK5CYII='));
    }
}
