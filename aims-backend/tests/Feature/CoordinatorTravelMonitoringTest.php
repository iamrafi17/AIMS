<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\HTE;
use App\Models\Program;
use App\Models\Student;
use App\Models\TravelLog;
use App\Models\TravelReport;
use App\Models\User;
use App\Support\TravelWorkflow;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoordinatorTravelMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_upload_private_photos_for_each_checkpoint_before_completion(): void
    {
        Http::fake();
        $disk = Storage::fake('local');
        [$studentUser, , $coordinator, $programHead] = $this->context();
        $this->scheduleFor($studentUser);
        Sanctum::actingAs($studentUser);
        $sessionId = $this->postJson('/api/student/travel/start', $this->coordinates())->assertCreated()->json('session.id');
        $departureId = $this->getJson('/api/student/travel')->json('current_session.points.0.id');
        $this->post("/api/student/travel/{$sessionId}/checkpoints/{$departureId}/photo", ['photo' => $this->image()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('session.points.0.has_photo', true);
        $firstPath = TravelLog::findOrFail($sessionId)->checkpoints()->findOrFail($departureId)->photo_path;
        $disk->assertExists($firstPath);
        $this->get("/api/student/travel/{$sessionId}/checkpoints/{$departureId}/photo")->assertOk();

        $pointId = $this->postJson("/api/student/travel/{$sessionId}/checkpoints", $this->coordinates())
            ->assertCreated()->json('session.points.1.id');
        $this->post("/api/student/travel/{$sessionId}/checkpoints/{$pointId}/photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $replacedPath = TravelLog::findOrFail($sessionId)->checkpoints()->findOrFail($pointId)->photo_path;
        $this->post("/api/student/travel/{$sessionId}/checkpoints/{$pointId}/photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $disk->assertMissing($replacedPath);
        $this->post("/api/student/travel/{$sessionId}/checkpoints/{$pointId}/photo", ['photo' => UploadedFile::fake()->create('note.txt', 1, 'text/plain')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->get("/api/student/travel/{$sessionId}/checkpoints/{$pointId}/photo")->assertOk();

        $otherStudent = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($otherStudent);
        $this->get("/api/student/travel/{$sessionId}/checkpoints/{$pointId}/photo")->assertNotFound();
        $this->post("/api/student/travel/{$sessionId}/checkpoints/{$pointId}/photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertNotFound();

        Sanctum::actingAs($coordinator);
        $this->get("/api/coordinator/travel/{$sessionId}/checkpoints/{$pointId}/photo")->assertOk();
        Sanctum::actingAs($programHead);
        $this->get("/api/program-head/travel/{$sessionId}/checkpoints/{$pointId}/photo")->assertOk();

        Sanctum::actingAs($studentUser);
        $this->postJson("/api/student/travel/{$sessionId}/arrival", $this->coordinates())->assertOk();
        $this->post("/api/student/travel/{$sessionId}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->postJson("/api/student/travel/{$sessionId}/end")->assertOk();
        $this->post("/api/student/travel/{$sessionId}/checkpoints/{$pointId}/photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->get("/api/student/travel/{$sessionId}/checkpoints/{$pointId}/photo")->assertOk();
    }

    public function test_final_arrival_card_uses_the_required_evidence_and_remains_private_and_locked(): void
    {
        Http::fake();
        $disk = Storage::fake('local');
        [$studentUser, , $coordinator, $programHead] = $this->context();
        $this->scheduleFor($studentUser);
        Sanctum::actingAs($studentUser);
        $id = $this->postJson('/api/student/travel/start', $this->coordinates())->assertCreated()->json('session.id');
        $arrivalId = $this->postJson("/api/student/travel/{$id}/arrival", $this->coordinates())
            ->assertOk()->json('session.points.1.id');
        $this->post("/api/student/travel/{$id}/arrival-photo", [
            'photo' => UploadedFile::fake()->create('invalid.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->post("/api/student/travel/{$id}/arrival-photo", [
            'photo' => UploadedFile::fake()->create('oversized.png', 5121, 'image/png'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->postJson("/api/student/travel/{$id}/end")->assertUnprocessable();

        // An old optional checkpoint upload must not satisfy the required arrival evidence.
        $this->post("/api/student/travel/{$id}/checkpoints/{$arrivalId}/photo", ['photo' => $this->image()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('session.points.1.has_photo', false);
        $this->postJson("/api/student/travel/{$id}/end")->assertUnprocessable();
        $response = $this->post("/api/student/travel/{$id}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('session.has_arrival_photo', true)
            ->assertJsonPath('session.points.1.has_photo', true);
        $firstPath = TravelLog::findOrFail($id)->arrival_photo_path;
        $firstEndpoint = $response->json('session.points.1.photo_endpoint');
        $this->assertStringStartsWith("/student/travel/{$id}/arrival-photo?", $firstEndpoint);
        $this->get('/api'.$firstEndpoint)->assertOk();

        $replacement = $this->post("/api/student/travel/{$id}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('session.points.1.has_photo', true);
        $this->assertNotSame($firstEndpoint, $replacement->json('session.points.1.photo_endpoint'));
        $disk->assertMissing($firstPath);
        $disk->assertExists(TravelLog::findOrFail($id)->arrival_photo_path);

        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->get('/api'.$replacement->json('session.points.1.photo_endpoint'))->assertNotFound();
        $this->post("/api/student/travel/{$id}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertNotFound();

        foreach ([[$coordinator, 'coordinator'], [$programHead, 'program-head']] as [$reviewer, $prefix]) {
            Sanctum::actingAs($reviewer);
            $session = TravelWorkflow::sessionPayload(TravelLog::findOrFail($id), "/{$prefix}/travel");
            $this->assertTrue($session['points'][1]['has_photo']);
            $this->assertStringStartsWith("/{$prefix}/travel/{$id}/arrival-photo?", $session['points'][1]['photo_endpoint']);
            $this->get('/api'.$session['points'][1]['photo_endpoint'])->assertOk();
        }

        Sanctum::actingAs($studentUser);
        $this->postJson("/api/student/travel/{$id}/end")->assertOk()->assertJsonPath('session.status', 'completed');
        $this->post("/api/student/travel/{$id}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->getJson('/api/student/travel')->assertOk()
            ->assertJsonPath('history.0.points.1.has_photo', true)
            ->assertJsonPath('history.0.points.1.photo_endpoint', $replacement->json('session.points.1.photo_endpoint'));
    }

    public function test_an_existing_active_session_can_still_record_its_legacy_departure(): void
    {
        Http::fake();
        [$studentUser] = $this->context();
        $this->scheduleFor($studentUser);
        Sanctum::actingAs($studentUser);
        $sessionId = $this->postJson('/api/student/travel/start', $this->coordinates())->assertCreated()->json('session.id');
        $travel = TravelLog::findOrFail($sessionId);
        $travel->update(['status' => 'active', 'departed_at' => null]);
        $travel->checkpoints()->firstOrFail()->update(['point_type' => 'start', 'checkpoint_name' => 'Starting Location']);

        $this->postJson("/api/student/travel/{$sessionId}/departure", $this->coordinates())
            ->assertOk()->assertJsonPath('session.status', 'traveling')
            ->assertJsonPath('session.points.1.event_type', 'departure');
        $this->postJson("/api/student/travel/{$sessionId}/checkpoints", $this->coordinates())
            ->assertCreated()->assertJsonPath('session.points.2.checkpoint_name', 'Checkpoint 1');
    }

    public function test_dean_can_return_a_travel_report_for_a_new_version(): void
    {
        Http::fake();
        Storage::fake('local');
        [$studentUser, $student, $coordinator, $programHead] = $this->context();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $student->college_id]);
        $sessionId = $this->completeSession($studentUser);
        Sanctum::actingAs($coordinator);
        $reportId = $this->postJson("/api/coordinator/travel/{$sessionId}/reports")->assertCreated()->json('session.report.id');
        $this->postJson("/api/coordinator/travel/reports/{$reportId}/submit")->assertOk();
        Sanctum::actingAs($programHead);
        $this->putJson("/api/program-head/travel/reports/{$reportId}/review")->assertOk();
        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/travel/reports/{$reportId}/review", [
            'decision' => 'returned', 'reason' => 'Clarify the route.',
        ])->assertOk()->assertJsonPath('report.status', 'draft')->assertJsonPath('report.version', 2);
        $this->assertDatabaseHas('travel_report_validations', [
            'travel_report_id' => $reportId, 'reviewer_role' => 'dean',
            'action' => 'returned', 'report_version' => 1,
        ]);
        Sanctum::actingAs($coordinator);
        $this->putJson("/api/coordinator/travel/reports/{$reportId}", ['remarks' => 'Route clarified.'])->assertOk();
        $this->postJson("/api/coordinator/travel/reports/{$reportId}/submit")->assertOk();
        Sanctum::actingAs($programHead);
        $this->putJson("/api/program-head/travel/reports/{$reportId}/review")->assertOk();
        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/travel/reports/{$reportId}/review")->assertOk()
            ->assertJsonPath('report.status', 'final_validated')->assertJsonPath('report.version', 2);
    }

    public function test_scheduled_student_session_and_report_completes_the_full_validation_chain(): void
    {
        Http::fake();
        /** @var FilesystemAdapter $privateDisk */
        $privateDisk = Storage::fake('local');
        [$studentUser, $student, $coordinator, $programHead, $vpaa, $hte] = $this->context();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $student->college_id]);

        Sanctum::actingAs($coordinator);
        $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id, 'scheduled_at' => now()->addMinutes(5)->toIso8601String(),
        ])->assertCreated()->assertJsonPath('session.status', 'scheduled');

        Sanctum::actingAs($studentUser);
        $sessionId = $this->postJson('/api/student/travel/start', $this->coordinates())
            ->assertCreated()
            ->assertJsonPath('session.status', 'traveling')
            ->assertJsonPath('session.student.id', $student->id)
            ->assertJsonPath('session.coordinator.id', $coordinator->id)
            ->assertJsonPath('session.hte.id', $hte->id)
            ->assertJsonPath('session.points.0.event_type', 'departure')
            ->assertJsonPath('session.points.0.checkpoint_name', 'Departure')
            ->json('session.id');

        $this->assertEquals(TravelLog::findOrFail($sessionId)->start_time, TravelLog::findOrFail($sessionId)->departed_at);
        $this->postJson("/api/student/travel/{$sessionId}/checkpoints", [
            ...$this->coordinates(13.48, 122.03),
            'remarks' => 'Traffic checkpoint',
        ])->assertCreated()->assertJsonPath('session.points.1.event_type', 'checkpoint')->assertJsonPath('session.points.1.checkpoint_name', 'Checkpoint 1');
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
        $this->assertSame($coordinator->id, $travel->created_by);
        $this->assertSame($coordinator->id, $travel->coordinator_id);
        $this->assertDatabaseCount('travel_checkpoints', 3);
        $privateDisk->assertExists($travel->arrival_photo_path);

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
        $this->putJson("/api/vpaa/travel/reports/{$reportId}/review")->assertForbidden();
        $otherCollege = College::create(['name' => 'Education', 'code' => 'CED', 'is_active' => true]);
        $otherDean = User::factory()->create(['role' => 'dean', 'college_id' => $otherCollege->id]);
        Sanctum::actingAs($otherDean->fresh());
        $this->getJson('/api/dean/travel')->assertOk()->assertJsonCount(0, 'sessions');
        $this->putJson("/api/dean/travel/reports/{$reportId}/review")->assertNotFound();
        Sanctum::actingAs($dean->fresh());
        $this->getJson('/api/dean/travel')->assertOk()->assertJsonPath('sessions.0.report.status', 'pending_vpaa');
        $this->putJson("/api/dean/travel/reports/{$reportId}/review", [
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
        $this->assertDatabaseHas('system_notifications', ['user_id' => $dean->id, 'title' => 'Travel report ready for final validation']);
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
        $this->putJson("/api/vpaa/travel/reports/{$reportId}/review")->assertForbidden();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $coordinator->college_id]);
        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/travel/reports/{$reportId}/review", [
            'decision' => 'returned', 'comments' => 'Revise the official remarks.',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertDatabaseHas('travel_reports', ['id' => $reportId, 'status' => 'pending_vpaa']);
        $this->putJson("/api/dean/travel/reports/{$reportId}/review", ['comments' => 'Final validation complete.'])
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
        $this->scheduleFor($studentUser);
        Sanctum::actingAs($studentUser);
        $sessionId = $this->postJson('/api/student/travel/start', $this->coordinates())->assertCreated()->json('session.id');
        $this->postJson("/api/student/travel/{$sessionId}/departure", $this->coordinates())->assertUnprocessable();
        $this->postJson('/api/student/travel/start', $this->coordinates())->assertUnprocessable()->assertJsonValidationErrors('session');

        $otherStudent = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($otherStudent);
        $this->postJson("/api/student/travel/{$sessionId}/departure", $this->coordinates())->assertNotFound();

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/travel/{$sessionId}/reports")
            ->assertUnprocessable()->assertJsonPath('message', 'A report can only be generated after the Student completes the travel session.');
        $this->postJson('/api/coordinator/travel/start', $this->coordinates())->assertStatus(405);

        Sanctum::actingAs($studentUser);
        $this->postJson("/api/student/travel/{$sessionId}/checkpoints", $this->coordinates(13.0, 121.0))
            ->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->postJson("/api/student/travel/{$sessionId}/arrival", $this->coordinates())->assertOk();
        $this->post("/api/student/travel/{$sessionId}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->postJson("/api/student/travel/{$sessionId}/end")->assertOk();
        Sanctum::actingAs($coordinator);
        $reportId = $this->postJson("/api/coordinator/travel/{$sessionId}/reports")->assertCreated()->json('session.report.id');
        $this->postJson("/api/coordinator/travel/reports/{$reportId}/submit")->assertOk();

        Sanctum::actingAs($vpaa);
        $this->putJson("/api/vpaa/travel/reports/{$reportId}/review")->assertForbidden();
        $dean = User::factory()->create(['role' => 'dean', 'college_id' => $coordinator->college_id]);
        Sanctum::actingAs($dean->fresh());
        $this->putJson("/api/dean/travel/reports/{$reportId}/review")->assertUnprocessable();

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
        $this->scheduleFor($studentUser);
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
        ])->assertCreated()->assertJsonPath('session.status', 'traveling');
    }

    public function test_coordinator_schedules_reschedules_and_cancels_with_student_notifications(): void
    {
        Http::fake();
        [$studentUser, $student, $coordinator] = $this->context();
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/travel/students?q=Ada')
            ->assertOk()->assertJsonPath('students.0.id', $student->id)
            ->assertJsonPath('students.0.hte.name', 'Partner Company');
        $this->getJson('/api/coordinator/travel/students?q=A')
            ->assertOk()->assertJsonPath('students.0.id', $student->id);
        $this->getJson('/api/coordinator/travel/students?q=Ada%20Intern')
            ->assertOk()->assertJsonPath('students.0.id', $student->id);

        $firstDate = now()->addDays(2)->setTime(6, 0);
        $id = $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id, 'scheduled_at' => $firstDate->toIso8601String(),
        ])->assertCreated()->assertJsonPath('session.status', 'scheduled')->json('session.id');
        $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id, 'scheduled_at' => $firstDate->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('student_id');

        Sanctum::actingAs($studentUser);
        $this->getJson('/api/student/travel')->assertOk()
            ->assertJsonPath('travel_requirement', 'scheduled')
            ->assertJsonPath('current_session.id', $id)
            ->assertJsonPath('can_start.allowed', false);
        $this->postJson('/api/student/travel/start', $this->coordinates())
            ->assertUnprocessable()->assertJsonValidationErrors('session');

        Sanctum::actingAs($coordinator);
        $newDate = now()->addDays(3)->setTime(7, 0);
        $this->putJson("/api/coordinator/travel/{$id}/schedule", [
            'scheduled_at' => $newDate->toIso8601String(),
        ])->assertOk()->assertJsonPath('session.status', 'scheduled');
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $studentUser->id, 'title' => 'Travel schedule changed',
        ]);
        $this->postJson("/api/coordinator/travel/{$id}/cancel")->assertOk();
        $this->assertDatabaseHas('travel_logs', ['id' => $id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $studentUser->id, 'title' => 'Travel schedule cancelled',
        ]);

        Sanctum::actingAs($studentUser);
        $this->getJson('/api/student/travel')->assertOk()
            ->assertJsonPath('travel_requirement', 'awaiting_schedule')
            ->assertJsonPath('current_session', null);
    }

    public function test_student_starts_on_scheduled_day_and_schedule_cannot_change_after_start(): void
    {
        Http::fake();
        [$studentUser, $student, $coordinator] = $this->context();
        Sanctum::actingAs($coordinator);
        $date = now()->addDays(2)->setTime(6, 0);
        $id = $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id, 'scheduled_at' => $date->toIso8601String(),
        ])->assertCreated()->json('session.id');

        $this->travelTo($date->copy()->setTime(5, 0));
        Sanctum::actingAs($studentUser);
        $this->getJson('/api/student/travel')->assertOk()->assertJsonPath('can_start.allowed', true);
        $this->postJson('/api/student/travel/start', $this->coordinates())
            ->assertCreated()->assertJsonPath('session.id', $id)
            ->assertJsonPath('session.status', 'traveling');

        Sanctum::actingAs($coordinator);
        $this->putJson("/api/coordinator/travel/{$id}/schedule", [
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(422);
        $this->postJson("/api/coordinator/travel/{$id}/cancel")->assertStatus(422);
    }

    public function test_local_hte_can_be_scheduled_without_a_province_field(): void
    {
        [$studentUser, $student, $coordinator, , , $hte] = $this->context();
        $hte->update(['address' => 'Santa Cruz, Marinduque']);
        Sanctum::actingAs($studentUser);
        $this->getJson('/api/student/travel')->assertOk()
            ->assertJsonPath('travel_requirement', 'awaiting_schedule');
        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinator/travel/students?q=Ada')->assertOk()
            ->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.id', $student->id)
            ->assertJsonPath('students.0.hte.name', 'Partner Company')
            ->assertJsonMissingPath('students.0.travel_note');
        $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id, 'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('session.status', 'scheduled');
        Sanctum::actingAs($studentUser);
        $this->getJson('/api/student/travel')->assertOk()
            ->assertJsonPath('travel_requirement', 'scheduled');
    }

    public function test_batch_schedule_creates_separate_sessions_and_notifies_each_student_for_a_date_only(): void
    {
        [$firstUser, $firstStudent, $coordinator] = $this->context();
        [$secondUser, $secondStudent] = $this->additionalStudent($firstStudent);

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinator/travel/students?q=Intern')->assertOk()
            ->assertJsonCount(2, 'students')
            ->assertJsonPath('students.0.school_number', '2026-4001')
            ->assertJsonPath('students.0.section', 'A');

        $date = now()->addDays(2)->toDateString();
        $this->postJson('/api/coordinator/travel/batch', [
            'student_ids' => [$firstStudent->id, $secondStudent->id],
            'travel_date' => now()->subDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('travel_date');
        $this->postJson('/api/coordinator/travel/batch', [
            'student_ids' => [$firstStudent->id, $secondStudent->id],
            'travel_date' => $date,
        ])->assertCreated()->assertJsonPath('count', 2)->assertJsonPath('travel_date', $date);

        $this->assertDatabaseCount('travel_logs', 2);
        $this->assertDatabaseCount('travel_participants', 2);
        foreach ([$firstStudent, $secondStudent] as $student) {
            $session = TravelLog::query()->whereHas('participants', fn ($query) => $query->whereKey($student->id))->firstOrFail();
            $this->assertSame($date, $session->scheduled_at->toDateString());
            $this->assertSame('00:00:00', $session->scheduled_at->format('H:i:s'));
            $this->assertDatabaseHas('system_notifications', [
                'user_id' => $student->user_id,
                'title' => 'Upcoming Travel Schedule',
                'message' => 'You have been scheduled for travel on '.now()->addDays(2)->format('F j, Y').'. Please check Travel Monitoring for more details.',
                'action_url' => '/student/travel',
            ]);
        }

        Sanctum::actingAs($secondUser);
        $this->getJson('/api/student/travel')->assertOk()
            ->assertJsonPath('current_session.scheduled_date', $date)
            ->assertJsonPath('current_session.status', 'scheduled')
            ->assertJsonPath('current_session.departed_at', null);

        $newDate = now()->addDays(3)->toDateString();
        $sessionId = TravelLog::query()->whereHas('participants', fn ($query) => $query->whereKey($secondStudent->id))->value('id');
        Sanctum::actingAs($coordinator);
        $this->putJson("/api/coordinator/travel/{$sessionId}/schedule", ['travel_date' => $newDate])
            ->assertOk()->assertJsonPath('session.scheduled_date', $newDate);
    }

    public function test_batch_schedule_rejects_an_existing_schedule_without_creating_partial_records(): void
    {
        [$firstUser, $firstStudent, $coordinator] = $this->context();
        [$secondUser, $secondStudent] = $this->additionalStudent($firstStudent);

        Sanctum::actingAs($coordinator);
        $date = now()->addDays(2)->toDateString();
        $this->postJson('/api/coordinator/travel/batch', [
            'student_ids' => [$secondStudent->id], 'travel_date' => $date,
        ])->assertCreated();
        $this->postJson('/api/coordinator/travel/batch', [
            'student_ids' => [$firstStudent->id, $secondStudent->id], 'travel_date' => $date,
        ])->assertUnprocessable()->assertJsonValidationErrors('student_ids');

        $this->assertDatabaseCount('travel_logs', 1);
        $this->assertDatabaseCount('travel_participants', 1);
        $this->assertDatabaseCount('system_notifications', 1);
    }

    public function test_travel_cannot_be_scheduled_before_deployment_approval(): void
    {
        [, $student, $coordinator] = $this->context();
        $student->update(['schedule_status' => 'pending']);

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinator/travel/students?q=Ada')
            ->assertOk()
            ->assertJsonCount(0, 'students');
        $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('student_id');
    }

    public function test_pending_internship_can_be_searched_by_name_and_school_id_before_ojt_start(): void
    {
        [, $student, $coordinator] = $this->context();
        $student->update(['internship_status' => 'pending', 'ojt_start_date' => now()->addDays(4)->toDateString()]);
        $before = $student->fresh()->currentInternship->getRawOriginal();

        Sanctum::actingAs($coordinator);
        foreach (['A', 'Ada', 'Intern', 'Ada Intern', '2026-4001', '4001'] as $term) {
            $this->getJson('/api/coordinator/travel/students?'.http_build_query(['q' => $term]))
                ->assertOk()->assertJsonCount(1, 'students')
                ->assertJsonPath('students.0.id', $student->id);
        }
        $this->assertSame($before, $student->fresh()->currentInternship->getRawOriginal());
    }

    public function test_pending_internship_can_be_scheduled_and_rescheduled_before_ojt_start(): void
    {
        [$studentUser, $student, $coordinator] = $this->context();
        $student->update(['internship_status' => 'pending', 'ojt_start_date' => now()->addDays(4)->toDateString()]);
        $before = $student->fresh()->currentInternship->getRawOriginal();
        $date = now()->addDays(2)->toDateString();

        Sanctum::actingAs($coordinator);
        $this->postJson('/api/coordinator/travel/batch', [
            'student_ids' => [$student->id], 'travel_date' => $date,
        ])->assertCreated()->assertJsonPath('count', 1)->assertJsonPath('travel_date', $date);
        $id = TravelLog::sole()->id;
        $this->putJson("/api/coordinator/travel/{$id}/schedule", ['travel_date' => now()->addDay()->toDateString()])
            ->assertOk()->assertJsonPath('session.scheduled_date', now()->addDay()->toDateString());
        $this->assertDatabaseHas('system_notifications', ['user_id' => $studentUser->id, 'title' => 'Upcoming Travel Schedule']);
        $this->assertSame($before, $student->fresh()->currentInternship->getRawOriginal());
    }

    public function test_pending_student_can_complete_travel_on_its_scheduled_day_before_ojt_starts(): void
    {
        Http::fake();
        Storage::fake('local');
        [$studentUser, $student, $coordinator] = $this->context();
        $ojtStart = now()->addDays(4)->startOfDay();
        $travelDate = $ojtStart->copy()->subDays(2);
        $student->update(['internship_status' => 'pending', 'ojt_start_date' => $ojtStart->toDateString()]);
        $before = $student->fresh()->currentInternship->getRawOriginal();

        Sanctum::actingAs($coordinator);
        $id = $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id, 'scheduled_at' => $travelDate->toIso8601String(),
        ])->assertCreated()->json('session.id');

        Sanctum::actingAs($studentUser);
        foreach ([now()->copy(), $travelDate->copy()->addDay()] as $wrongDay) {
            $this->travelTo($wrongDay);
            $this->getJson('/api/student/travel')->assertOk()->assertJsonPath('can_start.allowed', false);
            $this->postJson('/api/student/travel/start', $this->coordinates())
                ->assertUnprocessable()->assertJsonValidationErrors('session');
        }
        $this->travelTo($travelDate->copy()->setTime(8, 0));
        $this->getJson('/api/student/travel')->assertOk()->assertJsonPath('can_start.allowed', true);
        $this->postJson('/api/student/travel/start', $this->coordinates())
            ->assertCreated()->assertJsonPath('session.id', $id)->assertJsonPath('session.status', 'traveling');
        $this->postJson("/api/student/travel/{$id}/arrival", $this->coordinates())->assertOk();
        $this->post("/api/student/travel/{$id}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->postJson("/api/student/travel/{$id}/end")->assertOk()->assertJsonPath('session.status', 'completed');
        $this->assertSame($before, $student->fresh()->currentInternship->getRawOriginal());

        // Travel does not activate OJT; the existing start-date activation still does.
        $this->travelTo($ojtStart->copy()->setTime(8, 0));
        $this->getJson('/api/student/travel')->assertOk();
        $this->assertSame('active', $student->fresh()->internship_status);
        $this->assertSame($ojtStart->toDateString(), $student->fresh()->ojt_start_date->toDateString());
        $this->assertSame('approved', $student->fresh()->schedule_status);
    }

    #[DataProvider('ineligibleTravelStudents')]
    public function test_travel_requires_approved_deployment_start_date_and_current_placement(string $condition): void
    {
        Http::fake();
        [$studentUser, $student, $coordinator, , , $hte] = $this->context();
        $student->update(['internship_status' => 'pending', 'ojt_start_date' => now()->addDays(4)->toDateString()]);
        $session = TravelLog::create([
            'created_by' => $coordinator->id, 'coordinator_id' => $coordinator->id, 'hte_id' => $hte->id,
            'session_code' => 'TRV-PREREQUISITES', 'destination' => $hte->address,
            'scheduled_at' => today(), 'status' => 'scheduled',
        ]);
        $session->participants()->attach($student->id);
        match ($condition) {
            'completed', 'dropped' => $student->update(['internship_status' => $condition]),
            'pending', 'rejected' => $student->update(['schedule_status' => $condition]),
            'missing_start' => $student->update(['ojt_start_date' => null]),
            'inactive_hte' => $hte->update(['is_active' => false]),
            'missing_hte' => $student->fresh()->currentInternship->currentPlacement()->update(['is_current' => false]),
        };

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinator/travel/students?q=4001')->assertOk()->assertJsonCount(0, 'students');
        $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id, 'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->postJson('/api/coordinator/travel/batch', [
            'student_ids' => [$student->id], 'travel_date' => now()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->putJson("/api/coordinator/travel/{$session->id}/schedule", ['travel_date' => now()->addDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');

        Sanctum::actingAs($studentUser);
        $this->getJson('/api/student/travel')->assertOk()->assertJsonPath('can_start.allowed', false);
        $this->postJson('/api/student/travel/start', $this->coordinates())
            ->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->assertDatabaseCount('travel_logs', 1);
        $this->assertDatabaseCount('travel_checkpoints', 0);
    }

    public static function ineligibleTravelStudents(): array
    {
        return array_map(fn ($condition) => [$condition], [
            'completed', 'dropped', 'pending', 'rejected', 'missing_start', 'inactive_hte', 'missing_hte',
        ]);
    }

    public function test_pending_student_remains_scoped_to_the_assigned_coordinator(): void
    {
        [, $student, $coordinator] = $this->context();
        $student->update(['internship_status' => 'pending', 'ojt_start_date' => now()->addDays(4)->toDateString()]);
        $otherCoordinator = User::factory()->create([
            'role' => 'coordinator', 'college_id' => $coordinator->college_id, 'program_id' => $coordinator->program_id,
        ]);
        Sanctum::actingAs($otherCoordinator);
        $this->getJson('/api/coordinator/travel/students?q=4001')->assertOk()->assertJsonCount(0, 'students');
        $this->postJson('/api/coordinator/travel/batch', [
            'student_ids' => [$student->id], 'travel_date' => now()->addDay()->toDateString(),
        ])->assertNotFound();
        $this->assertDatabaseCount('travel_logs', 0);
    }

    public function test_travel_still_enforces_the_fixed_internship_end_date(): void
    {
        Http::fake();
        [$studentUser, $student, $coordinator] = $this->context();
        $end = $student->ojt_end_date->copy()->startOfDay();
        $afterEnd = $end->copy()->addDay();

        Sanctum::actingAs($coordinator);
        $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id, 'scheduled_at' => $afterEnd->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->postJson('/api/coordinator/travel/batch', [
            'student_ids' => [$student->id], 'travel_date' => $afterEnd->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $id = $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id, 'scheduled_at' => $end->toIso8601String(),
        ])->assertCreated()->json('session.id');
        $this->putJson("/api/coordinator/travel/{$id}/schedule", ['travel_date' => $afterEnd->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->assertSame($end->toDateString(), TravelLog::findOrFail($id)->scheduled_at->toDateString());

        $this->travelTo($afterEnd->setTime(8, 0));
        Sanctum::actingAs($studentUser);
        $this->getJson('/api/student/travel')->assertOk()->assertJsonPath('can_start.allowed', false);
        $this->postJson('/api/student/travel/start', $this->coordinates())
            ->assertUnprocessable()->assertJsonPath('errors.session.0', 'Travel cannot start after your internship end date.');
    }

    private function completeSession(User $studentUser): int
    {
        $this->scheduleFor($studentUser);
        Sanctum::actingAs($studentUser);
        $id = $this->postJson('/api/student/travel/start', $this->coordinates())->assertCreated()->json('session.id');
        $this->postJson("/api/student/travel/{$id}/checkpoint", $this->coordinates())->assertNotFound();
        $this->postJson("/api/student/travel/{$id}/checkpoints", $this->coordinates())->assertCreated();
        $this->postJson("/api/student/travel/{$id}/arrival", $this->coordinates())->assertOk();
        $this->post("/api/student/travel/{$id}/arrival-photo", ['photo' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->postJson("/api/student/travel/{$id}/end")->assertOk();

        return $id;
    }

    private function additionalStudent(Student $firstStudent): array
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::create([
            'user_id' => $user->id, 'student_id' => '2026-4002', 'first_name' => 'Grace',
            'last_name' => 'Intern', 'gender' => 'female', 'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque', 'contact_number' => '09173333333',
            'college_id' => $firstStudent->college_id, 'program_id' => $firstStudent->program_id,
            'academic_period_id' => $firstStudent->academic_period_id,
            'year_level' => $firstStudent->year_level, 'section' => $firstStudent->section,
            'parent_name' => 'Test Parent', 'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09174444444', 'hte_id' => $firstStudent->hte_id,
            'registration_status' => 'approved', 'internship_status' => 'active', 'schedule_status' => 'approved',
            'ojt_start_date' => now()->subDay()->toDateString(),
            'ojt_end_date' => now()->addMonth()->toDateString(),
        ]);

        return [$user, $student];
    }

    private function scheduleFor(User $studentUser): int
    {
        $student = $studentUser->student;
        Sanctum::actingAs(TravelWorkflow::coordinatorFor($student));
        return $this->postJson('/api/coordinator/travel', [
            'student_id' => $student->id,
            'scheduled_at' => now()->addMinutes(5)->toIso8601String(),
        ])->assertCreated()->json('session.id');
    }

    private function context(): array
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        $programHead = User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);
        $hte = HTE::create([
            'name' => 'Partner Company', 'address' => 'Manila, Metro Manila',
            'contact_person' => 'Supervisor', 'contact_email' => 'supervisor@example.com', 'contact_number' => '09170000000', 'latitude' => 13.47, 'longitude' => 122.02, 'is_active' => true,
        ]);
        $hte->programs()->attach($program->id, [
            'linked_by' => $coordinator->id,
            'is_active' => true,
        ]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $period = $this->academicPeriod();
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_id' => '2026-4001', 'first_name' => 'Ada', 'last_name' => 'Intern', 'gender' => 'female', 'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque', 'contact_number' => '09171111111', 'college_id' => $college->id, 'program_id' => $program->id, 'academic_period_id' => $period->id, 'year_level' => 4, 'section' => 'A',
            'parent_name' => 'Test Parent', 'parent_address' => 'Santa Cruz, Marinduque', 'parent_contact_number' => '09172222222', 'hte_id' => $hte->id,
            'registration_status' => 'approved', 'internship_status' => 'active', 'schedule_status' => 'approved', 'ojt_start_date' => now()->subDay()->toDateString(), 'ojt_end_date' => now()->addMonth()->toDateString(),
        ]);
        \Illuminate\Support\Facades\DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $student->academic_section_id, 'coordinator_id' => $coordinator->id,
            'created_at' => now(), 'updated_at' => now(),
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
