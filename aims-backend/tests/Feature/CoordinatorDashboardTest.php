<?php

namespace Tests\Feature;

use App\Models\AcademicSection;
use App\Models\ApprovalRecord;
use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\HteDepartment;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\TravelLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class CoordinatorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_dashboard_returns_operational_summaries(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $studentUser = User::factory()->create(['role' => 'student']);
        $college = College::create(['name' => 'Computing', 'code' => 'CC', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $coordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        $hte = HTE::create([
            'name' => 'Partner Company',
            'address' => 'Santa Cruz, Marinduque',
            'contact_person' => 'Supervisor',
            'contact_email' => 'supervisor@example.com',
            'contact_number' => '09170000000',
            'is_active' => true,
        ]);
        $hte->programs()->attach($program->id, [
            'linked_by' => $coordinator->id,
            'is_active' => true,
        ]);
        $department = HteDepartment::create([
            'hte_id' => $hte->id,
            'department_name' => 'Learning Resource Center',
            'normalized_name' => 'learning resource center',
        ]);
        $department->programs()->attach($program->id, [
            'linked_by' => $coordinator->id,
            'is_active' => true,
        ]);
        $supervisor = Supervisor::create([
            'hte_department_id' => $department->id,
            'full_name' => 'Maria Saint',
            'normalized_name' => 'maria saint',
        ]);

        $period = $this->academicPeriod('2026-2027', '1st Semester');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-0001',
            'first_name' => 'Test',
            'last_name' => 'Intern',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'contact_number' => '09171111111',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_period_id' => $period->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Test Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09172222222',
            'hte_id' => $hte->id,
            'hte_department_id' => $department->id,
            'supervisor_record_id' => $supervisor->id,
            'internship_year' => '2026-2027',
            'internship_semester' => 'First Semester',
            'registration_status' => 'approved',
            'internship_status' => 'active',
            'official_am_start' => '08:00',
            'official_am_end' => '12:00',
            'official_pm_start' => '13:00',
            'official_pm_end' => '17:00',
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'work_mode' => 'wfo',
            'session_type' => 'full_day',
            'status' => 'present',
            'am_time_in' => now()->setTime(8, 0),
            'am_time_out' => now()->setTime(12, 0),
            'pm_time_in' => now()->setTime(13, 0),
            'pm_time_out' => now()->setTime(17, 0),
            'am_activity' => 'Morning accomplishments',
            'pm_activity' => 'Afternoon accomplishments',
        ]);

        InternshipRequirement::create([
            'student_id' => $student->id,
            'requirement_name' => 'Consent Form',
            'file_path' => 'requirements/consent.pdf',
            'status' => 'approved',
            'reviewed_by' => $coordinator->id,
        ]);

        ApprovalRecord::create([
            'subject_type' => ApprovalRecord::TYPE_DEPLOYMENT,
            'subject_id' => $student->id,
            'status' => 'approved',
            'submitted_by' => $coordinator->id,
            'submitted_at' => now(),
        ]);

        MOA::create([
            'hte_id' => $hte->id,
            'program_id' => $program->id,
            'file_path' => 'moas/partner.pdf',
            'effective_date' => now()->subMonth(),
            'expiration_date' => now()->addYear(),
            'status' => 'approved',
        ]);

        $travel = TravelLog::create([
            'created_by' => $coordinator->id,
            'hte_id' => $hte->id,
            'session_code' => 'TRAVEL-001',
            'start_time' => now(),
            'status' => 'active',
        ]);
        $travel->participants()->attach($student->id);

        $otherProgram = Program::create(['college_id' => $college->id, 'name' => 'Computer Science', 'code' => 'BSCS', 'is_active' => true]);
        foreach ([
            ['2026-UNREGISTERED', $program->id, null],
            ['2026-REGISTERED', $program->id, now()],
            ['2026-OTHER-PROGRAM', $otherProgram->id, null],
        ] as [$schoolId, $programId, $registeredAt]) {
            $section = AcademicSection::where('program_id', $programId)->first()
                ?? AcademicSection::create([
                    'program_id' => $programId,
                    'academic_period_id' => $period->id,
                    'name' => 'A',
                    'year_level' => 4,
                    'academic_year' => '2026-2027',
                    'is_active' => true,
                ]);
            OjtEnrollment::create([
                'school_id' => $schoolId,
                'first_name' => 'Official Eligible', 'last_name' => 'Student',
                'academic_section_id' => $section->id,
                'source' => 'manual',
                'added_by' => $coordinator->id,
                'registered_at' => $registeredAt,
            ]);
        }

        \Illuminate\Support\Facades\DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $student->academic_section_id, 'coordinator_id' => $coordinator->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/dashboard')
            ->assertOk()
            ->assertJsonPath('overview.total_students', 1)
            ->assertJsonPath('overview.active', 1)
            ->assertJsonPath('overview.awaiting_registration', 1)
            ->assertJsonMissingPath('overview.pending_registrations')
            ->assertJsonPath('attendance.present', 1)
            ->assertJsonPath('attendance.rate', 100)
            ->assertJsonPath('attendance.total_hours', 8)
            ->assertJsonPath('journals.submitted', 2)
            ->assertJsonPath('journals.completion_rate', 100)
            ->assertJsonPath('requirements.approved', 1)
            ->assertJsonPath('requirements.completion_rate', 9.1)
            ->assertJsonPath('hte_moa.active_htes', 1)
            ->assertJsonPath('hte_moa.moa_approved', 1)
            ->assertJsonPath('travel.active', 1)
            ->assertJsonPath('active_interns.0.student_id', '2026-0001')
            ->assertJsonPath('active_interns.0.progress_percent', 1.6);

        $this->getJson('/api/coordinator/dashboard')
            ->assertJsonPath('internship_status.active', 1)
            ->assertJsonPath('attendance_today.present', 1)
            ->assertJsonPath('attendance_today.no_log', 0)
            ->assertJsonPath('ojt_progress.0_25', 1)
            ->assertJsonPath('requirement_summary.incomplete', 1);

        $this->getJson('/api/coordinator/internship-requirements')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.completed', 0)
            ->assertJsonPath('data.0.id', $student->id);

        $this->getJson('/api/coordinator/internship-requirements/'.$student->id)
            ->assertOk()
            ->assertJsonPath('overview.status', 'incomplete');

        $this->getJson('/api/coordinator/internship-requirements?status=incomplete')
            ->assertJsonPath('total', 1);
        $this->getJson('/api/coordinator/internship-requirements?status=completed')
            ->assertJsonPath('total', 0);
        $this->getJson('/api/coordinator/internship-requirements?search=not-a-student')
            ->assertJsonPath('total', 0);

        $unassignedCoordinator = User::factory()->create(['role' => 'coordinator']);
        $unassignedCoordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        Sanctum::actingAs($unassignedCoordinator);
        $this->getJson('/api/coordinator/internship-requirements')->assertJsonPath('summary.total', 0);
        $this->getJson('/api/coordinator/internship-requirements/'.$student->id)->assertNotFound();
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/coordinator/reports?date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('attendance.0.studentId', '2026-0001')
            ->assertJsonPath('attendance.0.amTimeIn', now()->setTime(8, 0)->format('g:i A'))
            ->assertJsonPath('students.0.studentId', '2026-0001')
            ->assertJsonPath('students.0.department', 'Learning Resource Center')
            ->assertJsonPath('students.0.supervisor', 'Maria Saint')
            ->assertJsonPath('students.0.workingSchedule', 'Mon, Tue, Wed, Thu, Fri | AM 8:00 - 12:00 | PM 1:00 - 5:00')
            ->assertJsonPath('students.0.remaining', 478)
            ->assertJsonPath('journals.0.submitted', 2)
            ->assertJsonPath('requirements.0.requirement', 'Consent Form')
            ->assertJsonPath('requirements.0.reviewedBy', $coordinator->name)
            ->assertJsonPath('deployments.0.hte', 'Partner Company')
            ->assertJsonPath('travel.0.id', 'TRAVEL-001');

        $export = $this->get('/api/coordinator/reports/export/csv?report=student_internship&date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk();
        /** @var BinaryFileResponse $exportResponse */
        $exportResponse = $export->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $exportResponse);
        $exportPath = $exportResponse->getFile()->getPathname();
        $this->assertStringContainsString('Student Internship Report', file_get_contents($exportPath));
        $this->assertStringContainsString('"Student Number","Student Name","Academic Year",Semester,HTE,"Working Schedule",Supervisor', file_get_contents($exportPath));
        $this->assertStringContainsString('2026-0001', file_get_contents($exportPath));
        $this->assertStringContainsString('Maria Saint', file_get_contents($exportPath));
        @unlink($exportPath);

        $hteExport = $this->get('/api/coordinator/reports/export/csv?report=hte_deployment')
            ->assertOk();
        /** @var BinaryFileResponse $hteExportResponse */
        $hteExportResponse = $hteExport->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $hteExportResponse);
        $hteExportPath = $hteExportResponse->getFile()->getPathname();
        $hteContents = file_get_contents($hteExportPath);
        $this->assertStringContainsString('Host Training Establishment Report', $hteContents);
        $this->assertStringContainsString('HTE,"HTE Address",Department,"Student Count","Working Schedule",Supervisor', $hteContents);
        $this->assertStringContainsString('Learning Resource Center', $hteContents);
        $this->assertStringContainsString('Maria Saint', $hteContents);
        @unlink($hteExportPath);
    }
}
