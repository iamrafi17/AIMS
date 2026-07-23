<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\HTE;
use App\Models\MOA;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
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
            ->assertJsonPath('colleges.0.id', $college->id);
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

    public function test_coordinator_can_manage_holidays_upload_moa_and_receive_expiration_alerts(): void
    {
        Storage::fake('public');
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
            'college_id' => $college->id,
            'effective_date' => now()->subMonth()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('moa.status', 'pending');

        $moa = MOA::firstOrFail();
        Storage::disk('public')->assertExists($moa->file_path);

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
        $hte = HTE::create([
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

        return [$coordinator, $college, $hte, $student];
    }
}
