<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\HTE;
use App\Models\Program;
use App\Models\Student;
use App\Models\TravelLog;
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

    public function test_setup_only_lists_database_students_assigned_to_the_selected_program_hte(): void
    {
        [$coordinator, $college, $program, $hte, $students] = $this->context();
        $otherProgram = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Systems',
            'code' => 'BSIS',
            'is_active' => true,
        ]);
        $otherHte = $this->hte($college, $otherProgram, 'Other Program Partner');
        $otherStudent = $this->student($college, $otherProgram, $otherHte, '2026-OTHER-01');
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/travel/setup')
            ->assertOk()
            ->assertJsonCount(1, 'htes')
            ->assertJsonPath('htes.0.id', $hte->id)
            ->assertJsonPath('htes.0.assigned_students_count', 2)
            ->assertJsonMissing(['id' => $otherHte->id]);

        $this->getJson("/api/coordinator/travel/htes/{$hte->id}/students")
            ->assertOk()
            ->assertJsonCount(2, 'students')
            ->assertJsonPath('students.0.hte.id', $hte->id)
            ->assertJsonPath('students.0.program.id', $program->id)
            ->assertJsonPath('students.0.department.id', $college->id)
            ->assertJsonMissing(['school_number' => $otherStudent->student_id]);

        $this->getJson("/api/coordinator/travel/htes/{$otherHte->id}/students")
            ->assertNotFound();
    }

    public function test_coordinator_can_complete_the_database_backed_capture_workflow(): void
    {
        Http::fake();
        Storage::fake('local');
        [$coordinator, , , $hte, $students] = $this->context();
        Sanctum::actingAs($coordinator);

        $sessionId = $this->postJson('/api/coordinator/travel/start', [
            'hte_id' => $hte->id,
            'student_ids' => $students->pluck('id')->all(),
            'latitude' => 13.475,
            'longitude' => 122.027,
        ])->assertCreated()
            ->assertJsonPath('session.status', 'active')
            ->assertJsonCount(2, 'session.participants')
            ->assertJsonCount(1, 'session.points')
            ->assertJsonPath('session.points.0.point_type', 'departure')
            ->assertJsonPath('session.points.0.sequence', 1)
            ->json('session.id');

        $this->assertDatabaseHas('travel_logs', [
            'id' => $sessionId,
            'created_by' => $coordinator->id,
            'hte_id' => $hte->id,
            'status' => 'active',
        ]);
        foreach ($students as $student) {
            $this->assertDatabaseHas('travel_participants', [
                'travel_log_id' => $sessionId,
                'student_id' => $student->id,
            ]);
        }

        $this->postJson("/api/coordinator/travel/{$sessionId}/departure", $this->coordinates())
            ->assertUnprocessable();

        $this->postJson("/api/coordinator/travel/{$sessionId}/arrival", [
            'latitude' => 13.48,
            'longitude' => 122.03,
        ])->assertCreated()
            ->assertJsonPath('session.status', 'active')
            ->assertJsonPath('point.point_type', 'arrival')
            ->assertJsonPath('point.is_final_destination', false)
            ->assertJsonPath('point.sequence', 2);

        $this->postJson("/api/coordinator/travel/{$sessionId}/departure", [
            'latitude' => 13.485,
            'longitude' => 122.035,
        ])->assertCreated()
            ->assertJsonPath('point.point_type', 'departure')
            ->assertJsonPath('point.sequence', 3);

        $this->postJson("/api/coordinator/travel/{$sessionId}/arrival", [
            'latitude' => 13.49,
            'longitude' => 122.04,
            'is_final_destination' => true,
        ])->assertCreated()
            ->assertJsonPath('session.status', 'active')
            ->assertJsonPath('point.point_type', 'arrival')
            ->assertJsonPath('point.is_final_destination', true)
            ->assertJsonPath('point.sequence', 4);

        $this->postJson("/api/coordinator/travel/{$sessionId}/departure", $this->coordinates())
            ->assertUnprocessable();
        $this->postJson("/api/coordinator/travel/{$sessionId}/end")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Upload the arrival photo before ending the session.');

        $this->post("/api/coordinator/travel/{$sessionId}/arrival-photo", [
            'photo' => UploadedFile::fake()->create('not-an-image.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->post("/api/coordinator/travel/{$sessionId}/arrival-photo", [
            'photo' => UploadedFile::fake()->createWithContent(
                'arrival.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nCEAAAAASUVORK5CYII=')
            ),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('session.status', 'active')
            ->assertJsonPath('session.has_arrival_photo', true);

        $session = TravelLog::findOrFail($sessionId);
        Storage::disk('local')->assertExists($session->arrival_photo_path);
        $this->get("/api/coordinator/travel/{$sessionId}/arrival-photo", [
            'Accept' => 'image/png',
        ])->assertOk();

        $this->postJson("/api/coordinator/travel/{$sessionId}/end")
            ->assertOk()
            ->assertJsonPath('session.status', 'completed')
            ->assertJsonPath('message', 'Travel session completed successfully.');

        $this->assertDatabaseHas('travel_logs', ['id' => $sessionId, 'status' => 'completed']);
        $this->assertDatabaseCount('travel_checkpoints', 4);
        $this->assertNotNull(TravelLog::findOrFail($sessionId)->end_time);
        $this->getJson('/api/coordinator/travel/active')->assertOk()->assertJsonPath('session', null);
        $this->getJson("/api/coordinator/travel/{$sessionId}")
            ->assertOk()
            ->assertJsonCount(4, 'session.points')
            ->assertJsonCount(2, 'session.participants');
    }

    public function test_active_session_and_points_are_restored_and_other_coordinators_cannot_modify_them(): void
    {
        Http::fake();
        [$coordinator, $college, $program, $hte, $students] = $this->context();
        Sanctum::actingAs($coordinator);
        $sessionId = $this->postJson('/api/coordinator/travel/start', [
            'hte_id' => $hte->id,
            'student_ids' => [$students->first()->id],
            ...$this->coordinates(),
        ])->assertCreated()->json('session.id');

        $this->getJson('/api/coordinator/travel/active')
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId)
            ->assertJsonPath('session.points.0.point_type', 'departure')
            ->assertJsonPath('session.participants.0.id', $students->first()->id);

        $otherCoordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ]);
        Sanctum::actingAs($otherCoordinator);

        $this->getJson("/api/coordinator/travel/{$sessionId}")->assertNotFound();
        $this->postJson("/api/coordinator/travel/{$sessionId}/arrival", $this->coordinates())->assertNotFound();
    }

    public function test_program_head_and_vpaa_reuse_the_coordinator_session_and_participants(): void
    {
        Http::fake();
        [$coordinator, $college, $program, $hte, $students] = $this->context();
        Sanctum::actingAs($coordinator);
        $sessionId = $this->postJson('/api/coordinator/travel/start', [
            'hte_id' => $hte->id,
            'student_ids' => $students->pluck('id')->all(),
            ...$this->coordinates(),
        ])->assertCreated()->json('session.id');

        $programHead = User::factory()->create([
            'role' => 'program_head',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ]);
        Sanctum::actingAs($programHead);
        $this->getJson('/api/program-head/travel')
            ->assertOk()
            ->assertJsonPath('sessions.0.id', $sessionId)
            ->assertJsonPath('sessions.0.coordinator', $coordinator->name)
            ->assertJsonCount(2, 'sessions.0.participants');

        Sanctum::actingAs(User::factory()->create(['role' => 'vpaa']));
        $this->getJson('/api/vpaa/travel')
            ->assertOk()
            ->assertJsonPath('sessions.0.id', $sessionId)
            ->assertJsonCount(2, 'sessions.0.participants');
    }

    public function test_start_rejects_invalid_gps_wrong_hte_students_and_a_second_unfinished_session(): void
    {
        Http::fake();
        [$coordinator, $college, $program, $hte, $students] = $this->context();
        $otherHte = $this->hte($college, $program, 'Second Partner');
        $otherStudent = $this->student($college, $program, $otherHte, '2026-OTHER-02');
        Sanctum::actingAs($coordinator);

        $this->postJson('/api/coordinator/travel/start', [
            'hte_id' => $hte->id,
            'student_ids' => [$students->first()->id],
            'latitude' => 120,
            'longitude' => 122.027,
        ])->assertUnprocessable()->assertJsonValidationErrors('latitude');

        $this->postJson('/api/coordinator/travel/start', [
            'hte_id' => $hte->id,
            'student_ids' => [$otherStudent->id],
            ...$this->coordinates(),
        ])->assertUnprocessable()->assertJsonValidationErrors('student_ids');

        $this->postJson('/api/coordinator/travel/start', [
            'hte_id' => $hte->id,
            'student_ids' => [$students->first()->id],
            ...$this->coordinates(),
        ])->assertCreated();

        $this->postJson('/api/coordinator/travel/start', [
            'hte_id' => $hte->id,
            'student_ids' => [$students->last()->id],
            ...$this->coordinates(),
        ])->assertUnprocessable()->assertJsonValidationErrors('session');
    }

    private function context(): array
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'BSIT',
            'is_active' => true,
        ]);
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ]);
        $hte = $this->hte($college, $program, 'Partner Company');
        $students = collect([
            $this->student($college, $program, $hte, '2026-4001', 'Ada'),
            $this->student($college, $program, $hte, '2026-4002', 'Grace'),
        ]);

        return [$coordinator, $college, $program, $hte, $students];
    }

    private function hte(College $college, Program $program, string $name): HTE
    {
        return HTE::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => $name,
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'contact_phone' => '09170000000',
            'latitude' => 13.47,
            'longitude' => 122.02,
            'is_active' => true,
        ]);
    }

    private function student(College $college, Program $program, HTE $hte, string $schoolNumber, string $firstName = 'Test'): Student
    {
        $user = User::factory()->create(['role' => 'student']);

        return Student::create([
            'user_id' => $user->id,
            'student_id' => $schoolNumber,
            'first_name' => $firstName,
            'last_name' => 'Intern',
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
            'hte_id' => $hte->id,
            'registration_status' => 'approved',
            'internship_status' => 'active',
        ]);
    }

    private function coordinates(): array
    {
        return ['latitude' => 13.475, 'longitude' => 122.027];
    }
}
