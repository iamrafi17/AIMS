<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\College;
use App\Models\HTE;
use App\Models\MOA;
use App\Models\Program;
use App\Models\ProgramOjtSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            'name' => '  New   Technology Partner  ',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Partner Supervisor',
            'contact_email' => 'partner@example.com',
            'contact_number' => '09170000001',
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

        $this->postJson('/api/coordinator/htes', [
            ...$payload,
            'contact_number' => '0917000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('contact_number');

        $hteId = $this->postJson('/api/coordinator/htes', $payload)
            ->assertCreated()
            ->assertJsonPath('hte.name', 'New Technology Partner')
            ->assertJsonPath('hte.geofence_enabled', true)
            ->json('hte.id');

        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('htes', 'normalized_name'));
        $this->postJson('/api/coordinator/htes', [...$payload, 'name' => 'new technology partner'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson("/api/coordinator/htes/{$hteId}", [...$payload, 'name' => '  PARTNER   COMPANY '])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

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

        $this->assertDatabaseCount('hte_schedules', 14);
        $this->assertDatabaseHas('hte_schedules', [
            'hte_id' => $hteId,
            'day_of_week' => 'sat',
            'is_working' => true,
            'am_start' => '08:00',
            'pm_end' => '17:00',
        ]);
    }

    public function test_geofence_edit_does_not_revalidate_an_unchanged_legacy_schedule(): void
    {
        [$coordinator, , $hte] = $this->context();
        Sanctum::actingAs($coordinator);
        $hte->update(['default_am_end' => '14:00']);
        $hte->refresh();

        $payload = [
            'name' => $hte->name,
            'address' => $hte->address,
            'contact_person' => $hte->contact_person,
            'contact_email' => $hte->contact_email,
            'contact_number' => $hte->contact_number,
            'latitude' => 13.46573159,
            'longitude' => 122.05900570,
            'geofence_radius' => 100,
            'geofence_enabled' => true,
            'default_am_start' => '08:00',
            'default_am_end' => '14:00',
            'default_pm_start' => '13:00',
            'default_pm_end' => '17:00',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
        ];

        $this->putJson("/api/coordinator/htes/{$hte->id}", $payload)
            ->assertOk()
            ->assertJsonPath('hte.geofence_enabled', true)
            ->assertJsonPath('hte.default_am_end', '14:00');
        $this->assertDatabaseHas('htes', ['id' => $hte->id, 'geofence_enabled' => true]);
        $this->assertDatabaseHas('hte_schedules', ['hte_id' => $hte->id, 'day_of_week' => 'mon', 'am_end' => '14:00']);

        $this->putJson("/api/coordinator/htes/{$hte->id}", [
            ...$payload,
            'default_am_end' => '15:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('default_am_end');
    }

    public function test_coordinator_can_create_an_hte_with_a_normalized_per_day_schedule(): void
    {
        [$coordinator] = $this->context();
        Sanctum::actingAs($coordinator);
        $days = $this->batchScheduleDays();

        $hteId = $this->postJson('/api/coordinator/htes', [
            'name' => 'Normalized Schedule Partner',
            'address' => 'Boac, Marinduque',
            'contact_person' => 'Schedule Supervisor',
            'contact_email' => 'normalized-schedule@example.test',
            'contact_number' => '09170000019',
            'latitude' => null,
            'longitude' => null,
            'geofence_radius' => 100,
            'geofence_enabled' => false,
            'schedule' => [
                'grace_period_minutes' => 12,
                'days' => $days,
            ],
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('hte.schedule.configured', true)
            ->assertJsonPath('hte.schedule.grace_period_minutes', 12)
            ->assertJsonPath('hte.schedule.days.mon.am_start', '08:00')
            ->assertJsonPath('hte.schedule.days.sat.is_working', false)
            ->assertJsonPath('hte.schedule.days.sat.am_start', null)
            ->json('hte.id');

        $this->assertSame(7, DB::table('hte_schedules')->where('hte_id', $hteId)->count());
        $this->assertDatabaseHas('hte_schedules', [
            'hte_id' => $hteId,
            'day_of_week' => 'mon',
            'is_working' => true,
            'grace_period_minutes' => 12,
            'am_start' => '08:00',
            'pm_end' => '17:00',
        ]);
        $this->assertDatabaseHas('hte_schedules', [
            'hte_id' => $hteId,
            'day_of_week' => 'sun',
            'is_working' => false,
            'am_start' => null,
            'pm_end' => null,
        ]);
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
            'contact_number' => '09170000009',
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
                'grace_period_minutes' => 15,
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
            'day_of_week' => 'sat',
            'is_working' => true,
            'grace_period_minutes' => 15,
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
            ->assertJsonPath('student.internship_status', 'pending')
            ->assertJsonCount(6, 'student.work_days');

        $this->assertDatabaseHas('student_internships', [
            'student_id' => $student->id,
            'status' => 'pending',
            'deployment_status' => 'pending',
            'allow_past_attendance' => true,
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('student_internship_placements', [
            'student_internship_id' => $student->fresh()->currentInternship->id,
            'hte_program_id' => DB::table('hte_programs')
                ->where('hte_id', $hte->id)
                ->where('program_id', $coordinator->program_id)
                ->value('id'),
            'hte_department_program_id' => null,
            'assigned_by' => $coordinator->id,
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('student_schedule_overrides', [
            'student_internship_id' => $student->fresh()->currentInternship->id,
            'day_of_week' => 'sat',
            'am_start' => '07:30',
            'pm_end' => '16:30',
            'source' => 'manual',
        ]);
    }

    public function test_deployment_uses_the_batch_schedule_by_default_and_keeps_custom_hours_as_an_exception(): void
    {
        [$coordinator, , $hte, $student, $batch] = $this->context();
        Sanctum::actingAs($coordinator);
        $days = $this->batchScheduleDays();
        foreach (['mon', 'tue', 'wed', 'thu', 'fri'] as $day) {
            $days[$day]['am_start'] = '07:30';
            $days[$day]['am_end'] = '11:30';
            $days[$day]['pm_end'] = '16:30';
        }
        $this->putJson("/api/coordinator/htes/{$hte->id}/batch-schedules/{$batch->id}", [
            'grace_period_minutes' => 10,
            'days' => $days,
        ])->assertOk();

        $base = [
            'hte_id' => $hte->id,
            'hte_department_id' => null,
            'supervisor_record_id' => null,
            'supervisor_id' => null,
            'ojt_start_date' => '2026-08-01',
            'ojt_end_date' => '2026-12-15',
            'required_ojt_hours' => 486,
            'internship_status' => 'active',
            'allow_past_attendance' => false,
        ];

        $this->putJson("/api/coordinator/htes/deployments/{$student->id}", [
            ...$base,
            'schedule_mode' => 'custom',
            'official_am_start' => '07:00',
            'official_am_end' => '11:30',
            'official_pm_start' => '13:00',
            'official_pm_end' => '16:30',
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ])->assertOk();
        $this->assertDatabaseHas('student_schedule_overrides', [
            'day_of_week' => 'mon',
            'am_start' => '07:00',
            'source' => 'manual',
        ]);

        $this->putJson("/api/coordinator/htes/deployments/{$student->id}", [
            ...$base,
            'schedule_mode' => 'batch',
        ])->assertOk();

        $this->assertDatabaseMissing('student_schedule_overrides', [
            'student_internship_id' => $student->fresh()->currentInternship->id,
        ]);
        $this->assertDatabaseHas('hte_batch_schedules', [
            'hte_id' => $hte->id,
            'academic_section_id' => $batch->id,
            'day_of_week' => 'mon',
            'am_start' => '07:30',
            'am_end' => '11:30',
            'pm_end' => '16:30',
        ]);

        $this->putJson("/api/coordinator/htes/deployments/{$student->id}", [
            ...$base,
            'schedule_mode' => 'custom',
            'official_am_start' => '07:00',
            'official_am_end' => '23:30',
            'official_pm_start' => '13:00',
            'official_pm_end' => '16:30',
            'work_days' => ['mon'],
        ])->assertUnprocessable()->assertJsonValidationErrors('official_am_end');
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

        $this->putJson("/api/coordinator/htes/{$hte->id}/batch-schedules/{$batch->id}", ['grace_period_minutes' => 7, 'days' => $days])
            ->assertOk()
            ->assertJsonPath('message', 'Batch schedule saved successfully.')
            ->assertJsonPath('batch.has_saved_schedule', true)
            ->assertJsonPath('batch.grace_period_minutes', 7)
            ->assertJsonPath('batch.days.mon.am_start', '08:00')
            ->assertJsonPath('batch.days.sat.am_start', '09:00')
            ->assertJsonPath('batch.days.sat.pm_end', '15:00');

        $this->assertDatabaseCount('hte_batch_schedules', 7);

        $overlappingDays = $days;
        $overlappingDays['mon']['am_end'] = '23:30';
        $this->putJson("/api/coordinator/htes/{$hte->id}/batch-schedules/{$batch->id}", ['days' => $overlappingDays])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('days.mon.am_end');
        $this->assertDatabaseHas('hte_batch_schedules', [
            'hte_id' => $hte->id,
            'academic_section_id' => $batch->id,
            'day_of_week' => 'mon',
            'am_end' => '12:00',
        ]);
        $this->assertDatabaseHas('hte_batch_schedules', [
            'hte_id' => $hte->id,
            'academic_section_id' => $batch->id,
            'day_of_week' => 'sat',
            'is_working' => true,
            'grace_period_minutes' => 7,
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
            'academic_period_id' => $this->academicPeriod()->id,
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
            'holiday_type' => 'special_non_working',
            'official_reference' => 'University Memorandum No. 15',
            'description' => 'University non-working day.',
            'is_recurring' => true,
        ])->assertCreated()->json('holiday.id');

        $this->putJson("/api/coordinator/htes/holidays/{$holidayId}", [
            'name' => 'University Foundation Day',
            'date' => '2026-09-15',
            'holiday_type' => 'special_non_working',
            'official_reference' => 'University Memorandum No. 15',
            'description' => 'Updated holiday description.',
            'is_recurring' => true,
        ])->assertOk()->assertJsonPath('holiday.name', 'University Foundation Day');

        $this->post('/api/coordinator/htes/moas', [
            'hte_id' => $hte->id,
            'program_id' => $coordinator->program_id,
            'effective_date' => now()->subMonth()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('moa.status', 'pending')
            ->assertJsonPath('moa.college.id', $college->id)
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
            ->assertStatus(422)
            ->assertJsonPath('message', 'MOA under review cannot be deleted.');
        $this->assertDatabaseHas('moas', ['id' => $moa->id]);
        $privateDisk->assertExists($moa->file_path);

        $this->deleteJson("/api/coordinator/htes/{$hte->id}")
            ->assertOk()
            ->assertJsonPath('message', 'HTE removed from this program. Shared HTE data, deployments, and MOA history were preserved.');
        $this->assertDatabaseHas('htes', ['id' => $hte->id, 'is_active' => true]);
        $this->assertDatabaseHas('hte_programs', ['hte_id' => $hte->id, 'program_id' => $coordinator->program_id, 'is_active' => false]);
        $this->assertDatabaseHas('moas', ['id' => $moa->id]);
        $privateDisk->assertExists($moa->file_path);

        $this->deleteJson("/api/coordinator/htes/holidays/{$holidayId}")
            ->assertOk();
        $this->assertDatabaseMissing('holidays', ['id' => $holidayId]);
    }

    public function test_coordinator_can_remove_unreviewed_pending_moa_without_erasing_its_record_or_file(): void
    {
        Storage::fake('local');
        [$coordinator, , $hte] = $this->context();
        Sanctum::actingAs($coordinator);

        $moaId = $this->post('/api/coordinator/htes/moas', [
            'hte_id' => $hte->id,
            'program_id' => $coordinator->program_id,
            'effective_date' => now()->subDay()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('moa.id');
        $path = MOA::findOrFail($moaId)->file_path;

        $this->deleteJson("/api/coordinator/htes/moas/{$moaId}")
            ->assertOk()
            ->assertJsonPath('message', 'MOA deleted.');

        $this->assertSoftDeleted('moas', ['id' => $moaId]);
        /** @var FilesystemAdapter $privateDisk */
        $privateDisk = Storage::disk('local');
        $privateDisk->assertExists($path);
        $this->getJson('/api/coordinator/htes')->assertOk()->assertJsonCount(0, 'moas');
        $this->getJson("/api/coordinator/htes/moas/{$moaId}/download")->assertNotFound();
    }

    public function test_coordinator_can_edit_pending_moa_dates_but_not_after_program_head_review(): void
    {
        Storage::fake('local');
        [$coordinator, , $hte] = $this->context();
        Sanctum::actingAs($coordinator);

        $moaId = $this->post('/api/coordinator/htes/moas', [
            'hte_id' => $hte->id,
            'program_id' => $coordinator->program_id,
            'effective_date' => now()->subDay()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('moa.id');

        $newEffectiveDate = today()->toDateString();
        $newExpirationDate = today()->addMonths(6)->toDateString();
        $this->putJson("/api/coordinator/htes/moas/{$moaId}", [
            'effective_date' => $newEffectiveDate,
            'expiration_date' => $newExpirationDate,
        ])->assertOk()->assertJsonPath('message', 'MOA details updated and returned to pending approval.');

        $moa = MOA::findOrFail($moaId);
        $this->assertSame($newEffectiveDate, $moa->effective_date->toDateString());
        $this->assertSame($newExpirationDate, $moa->expiration_date->toDateString());

        $moa->update(['program_status' => 'endorsed', 'program_reviewed_at' => now()]);
        $this->putJson("/api/coordinator/htes/moas/{$moaId}", [
            'effective_date' => today()->addDay()->toDateString(),
            'expiration_date' => today()->addYear()->toDateString(),
        ])->assertUnprocessable();
        $this->deleteJson("/api/coordinator/htes/moas/{$moaId}")->assertUnprocessable();

        $moa->update(['program_status' => 'returned', 'program_feedback' => 'Please revise.', 'status' => 'pending']);
        $this->putJson("/api/coordinator/htes/moas/{$moaId}", [
            'effective_date' => today()->toDateString(),
            'expiration_date' => today()->addYear()->toDateString(),
        ])->assertOk()->assertJsonPath('moa.program_status', 'pending');
        $this->assertDatabaseHas('moas', [
            'id' => $moaId,
            'program_status' => 'pending',
            'program_feedback' => null,
            'program_reviewed_at' => null,
        ]);
    }

    private function context(): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $studentUser = User::factory()->create(['role' => 'student']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $coordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        $hte = HTE::create([
            'name' => 'Partner Company',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_number' => '09170000000',
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
        DB::table('hte_programs')->insert([
            'hte_id' => $hte->id,
            'program_id' => $program->id,
            'linked_by' => $coordinator->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $period = $this->academicPeriod();
        ProgramOjtSetting::create([
            'program_id' => $program->id, 'academic_period_id' => $period->id,
            'required_hours' => 486, 'ojt_start_date' => '2026-08-01',
            'status' => 'published', 'published_at' => now(),
        ]);
        $batch = AcademicSection::create([
            'program_id' => $program->id,
            'academic_period_id' => $period->id,
            'name' => 'A',
            'year_level' => 4,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $batch->id, 'coordinator_id' => $coordinator->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-4001',
            'first_name' => 'Test',
            'last_name' => 'Intern',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'contact_number' => '09171111111',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $batch->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09172222222',
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
