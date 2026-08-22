<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\College;
use App\Models\HTE;
use App\Models\MOA;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorHTEManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_create_update_and_view_hte_operations_data(): void
    {
        [$coordinator, $college] = $this->context();
        Sanctum::actingAs($coordinator);

        $payload = [
            'name' => 'New Technology Partner',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Partner Supervisor',
            'contact_email' => 'partner@example.com',
            'contact_phone' => '09170000001',
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

        $hteId = $this->postJson('/api/coordinator/htes', $payload)
            ->assertCreated()
            ->assertJsonPath('hte.name', 'New Technology Partner')
            ->assertJsonPath('hte.geofence_enabled', true)
            ->json('hte.id');

        $payload['geofence_radius'] = 250;
        $payload['work_days'][] = 'sat';

        $this->putJson("/api/coordinator/htes/{$hteId}", $payload)
            ->assertOk()
            ->assertJsonPath('hte.geofence_radius', 250)
            ->assertJsonCount(6, 'hte.work_days');

        $this->getJson('/api/coordinator/htes')
            ->assertOk()
            ->assertJsonPath('summary.total_htes', 2)
            ->assertJsonPath('summary.geofenced_htes', 1)
            ->assertJsonCount(2, 'htes')
            ->assertJsonPath('colleges.0.id', $college->id)
            ->assertJsonPath('batches.0.id', AcademicSection::firstOrFail()->id);
    }

    public function test_coordinator_can_create_an_hte_and_initial_batch_schedule_in_one_request(): void
    {
        [$coordinator, , , , $batch] = $this->context();
        Sanctum::actingAs($coordinator);

        $days = $this->batchScheduleDays();
        $days['sat'] = [
            'is_working' => true,
            'am_start' => '07:30',
            'am_end' => '11:30',
            'pm_start' => '13:00',
            'pm_end' => '16:00',
        ];
        $payload = [
            'name' => 'Integrated Schedule Partner',
            'address' => 'Boac, Marinduque',
            'contact_person' => 'Initial Supervisor',
            'contact_email' => 'integrated@example.com',
            'contact_phone' => '09170000009',
            'latitude' => null,
            'longitude' => null,
            'geofence_radius' => 100,
            'geofence_enabled' => false,
            'default_am_start' => '08:00',
            'default_am_end' => '12:00',
            'default_pm_start' => '13:00',
            'default_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'batch_schedule' => [
                'academic_section_id' => $batch->id,
                'days' => $days,
            ],
        ];

        $hteId = $this->postJson('/api/coordinator/htes', $payload)
            ->assertCreated()
            ->assertJsonPath('message', 'HTE record and batch schedule created successfully.')
            ->assertJsonPath('hte.name', 'Integrated Schedule Partner')
            ->assertJsonPath('hte.batch_schedule_summary.batch_id', $batch->id)
            ->assertJsonPath('hte.batch_schedule_summary.groups.1.days.0', 'sat')
            ->json('hte.id');

        $this->assertDatabaseHas('hte_batch_schedules', [
            'hte_id' => $hteId,
            'academic_section_id' => $batch->id,
            'day_of_week' => 'sat',
            'is_working' => true,
            'am_start' => '07:30',
            'pm_end' => '16:00',
        ]);
        $this->assertSame(7, HTE::findOrFail($hteId)->batchSchedules()->count());

        $payload['name'] = 'Invalid Integrated Partner';
        $payload['batch_schedule']['days']['mon']['am_end'] = '07:00';
        $this->postJson('/api/coordinator/htes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('batch_schedule.days.mon.am_end');
        $this->assertDatabaseMissing('htes', ['name' => 'Invalid Integrated Partner']);

        $payload['name'] = 'No Working Days Partner';
        $payload['batch_schedule']['days']['mon']['am_end'] = '12:00';
        foreach ($payload['batch_schedule']['days'] as &$day) {
            $day['is_working'] = false;
        }
        unset($day);
        $this->postJson('/api/coordinator/htes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('batch_schedule.days');
        $this->assertDatabaseMissing('htes', ['name' => 'No Working Days Partner']);
    }

    public function test_coordinator_can_configure_student_deployment_and_working_hours(): void
    {
        [$coordinator, , $hte, $student] = $this->context();
        Sanctum::actingAs($coordinator);

        $this->putJson("/api/coordinator/htes/deployments/{$student->id}", [
            'hte_id' => $hte->id,
            'ojt_start_date' => '2026-08-01',
            'ojt_end_date' => '2026-12-15',
            'required_ojt_hours' => 486,
            'official_am_start' => '07:30',
            'official_am_end' => '11:30',
            'official_pm_start' => '12:30',
            'official_pm_end' => '16:30',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
            'internship_status' => 'active',
            'allow_past_attendance' => true,
        ])->assertOk()
            ->assertJsonPath('student.hte_id', $hte->id)
            ->assertJsonPath('student.internship_status', 'active')
            ->assertJsonCount(6, 'student.work_days');

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'hte_id' => $hte->id,
            'official_am_start' => '07:30',
            'official_pm_end' => '16:30',
            'allow_past_attendance' => true,
        ]);
    }

    public function test_coordinator_can_save_and_retrieve_an_editable_per_day_batch_schedule(): void
    {
        [$coordinator, , $hte, , $batch] = $this->context();
        Sanctum::actingAs($coordinator);

        $this->getJson("/api/coordinator/htes/{$hte->id}/batch-schedules")
            ->assertOk()
            ->assertJsonPath('hte.id', $hte->id)
            ->assertJsonPath('batches.0.id', $batch->id)
            ->assertJsonPath('batches.0.has_saved_schedule', false)
            ->assertJsonPath('batches.0.days.mon.am_start', '08:00');

        $days = $this->batchScheduleDays();
        $days['sat'] = [
            'is_working' => true,
            'am_start' => '09:00',
            'am_end' => '12:30',
            'pm_start' => '13:30',
            'pm_end' => '15:00',
        ];

        $this->putJson("/api/coordinator/htes/{$hte->id}/batch-schedules/{$batch->id}", ['days' => $days])
            ->assertOk()
            ->assertJsonPath('message', 'Batch schedule saved successfully.')
            ->assertJsonPath('batch.has_saved_schedule', true)
            ->assertJsonPath('batch.days.mon.am_start', '08:00')
            ->assertJsonPath('batch.days.sat.am_start', '09:00')
            ->assertJsonPath('batch.days.sat.pm_end', '15:00');

        $this->assertDatabaseCount('hte_batch_schedules', 7);
        $this->assertDatabaseHas('hte_batch_schedules', [
            'hte_id' => $hte->id,
            'academic_section_id' => $batch->id,
            'day_of_week' => 'sat',
            'is_working' => true,
            'am_start' => '09:00',
            'pm_end' => '15:00',
        ]);

        $this->getJson('/api/coordinator/htes')
            ->assertOk()
            ->assertJsonPath('htes.0.batch_schedule_summary.batch_id', $batch->id)
            ->assertJsonPath('htes.0.batch_schedule_summary.configured_batches', 1)
            ->assertJsonPath('htes.0.batch_schedule_summary.groups.1.am_start', '09:00')
            ->assertJsonPath('htes.0.batch_schedule_summary.groups.1.days.0', 'sat');

        $days['sat']['am_end'] = '08:30';
        $this->putJson("/api/coordinator/htes/{$hte->id}/batch-schedules/{$batch->id}", ['days' => $days])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('days.sat.am_end');
        $this->assertDatabaseCount('hte_batch_schedules', 7);
    }

    public function test_coordinator_cannot_configure_a_batch_from_another_program(): void
    {
        [$coordinator, $college, $hte] = $this->context();
        $otherProgram = Program::create([
            'college_id' => $college->id,
            'name' => 'Computer Science',
            'code' => 'BSCS',
            'is_active' => true,
        ]);
        $otherBatch = AcademicSection::create([
            'program_id' => $otherProgram->id,
            'name' => 'A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        Sanctum::actingAs($coordinator);

        $this->putJson("/api/coordinator/htes/{$hte->id}/batch-schedules/{$otherBatch->id}", [
            'days' => $this->batchScheduleDays(),
        ])->assertNotFound();

        $this->assertDatabaseCount('hte_batch_schedules', 0);
    }

    public function test_coordinator_can_manage_holidays_upload_moa_and_receive_expiration_alerts(): void
    {
        Storage::fake('local');
        [$coordinator, $college, $hte] = $this->context();
        Sanctum::actingAs($coordinator);

        $holidayId = $this->postJson('/api/coordinator/htes/holidays', [
            'name' => 'Campus Foundation Day',
            'date' => '2026-09-15',
            'description' => 'University non-working day.',
            'is_recurring' => true,
        ])->assertCreated()->json('holiday.id');

        $this->putJson("/api/coordinator/htes/holidays/{$holidayId}", [
            'name' => 'University Foundation Day',
            'date' => '2026-09-15',
            'description' => 'Updated holiday description.',
            'is_recurring' => true,
        ])->assertOk()->assertJsonPath('holiday.name', 'University Foundation Day');

        $this->post('/api/coordinator/htes/moas', [
            'hte_id' => $hte->id,
            'effective_date' => now()->subMonth()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('moa.status', 'pending')
            ->assertJsonPath('moa.college_id', $college->id)
            ->assertJsonPath('moa.program_id', $coordinator->program_id);

        $moa = MOA::firstOrFail();
        /** @var FilesystemAdapter $privateDisk */
        $privateDisk = Storage::disk('local');
        $privateDisk->assertExists($moa->file_path);

        $this->get("/api/coordinator/htes/moas/{$moa->id}/download")
            ->assertOk()
            ->assertDownload("MOA-partner-company-{$moa->id}.pdf");

        $moa->update([
            'status' => 'approved',
            'approved_by' => $coordinator->id,
            'approved_at' => now(),
            'expiration_date' => now()->addDays(20),
        ]);

        $this->getJson('/api/coordinator/htes')
            ->assertOk()
            ->assertJsonPath('summary.valid_moas', 1)
            ->assertJsonPath('summary.expiring_moas', 1)
            ->assertJsonPath('expiration_alerts.0.hte', $hte->name)
            ->assertJsonPath('expiration_alerts.0.level', 'critical');

        $this->deleteJson("/api/coordinator/htes/moas/{$moa->id}")
            ->assertStatus(405);
        $this->assertDatabaseHas('moas', ['id' => $moa->id]);
        $privateDisk->assertExists($moa->file_path);

        $this->deleteJson("/api/coordinator/htes/{$hte->id}")
            ->assertOk()
            ->assertJsonPath('message', 'HTE deactivated. Deployments and MOA history were preserved.');
        $this->assertDatabaseHas('htes', ['id' => $hte->id, 'is_active' => false]);
        $this->assertDatabaseHas('moas', ['id' => $moa->id]);
        $privateDisk->assertExists($moa->file_path);

        $this->deleteJson("/api/coordinator/htes/holidays/{$holidayId}")
            ->assertOk();
        $this->assertDatabaseMissing('holidays', ['id' => $holidayId]);
    }

    private function context(): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $studentUser = User::factory()->create(['role' => 'student']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $coordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        $hte = HTE::create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'name' => 'Partner Company',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_phone' => '09170000000',
            'latitude' => 13.47,
            'longitude' => 122.02,
            'geofence_radius' => 100,
            'geofence_enabled' => false,
            'default_am_start' => '08:00',
            'default_am_end' => '12:00',
            'default_pm_start' => '13:00',
            'default_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
        ]);
        $batch = AcademicSection::create([
            'program_id' => $program->id,
            'name' => 'A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-4001',
            'first_name' => 'Test',
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
            'registration_status' => 'approved',
            'internship_status' => 'pending',
        ]);

        return [$coordinator, $college, $hte, $student, $batch];
    }

    private function batchScheduleDays(): array
    {
        return collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
            ->mapWithKeys(fn (string $day) => [$day => [
                'is_working' => in_array($day, ['mon', 'tue', 'wed', 'thu', 'fri'], true),
                'am_start' => '08:00',
                'am_end' => '12:00',
                'pm_start' => '13:00',
                'pm_end' => '17:00',
            ]])
            ->all();
    }
}
