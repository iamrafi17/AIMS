<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\College;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_head_report_is_date_filtered_and_program_scoped(): void
    {
        [$college, $program] = $this->academicScope('CC', 'BSIT');
        [, $otherProgram] = $this->academicScope('CAS', 'BAPOLSCI');
        $student = $this->student($college, $program, '2026-1001');
        $otherStudent = $this->student($otherProgram->college, $otherProgram, '2026-2001');
        $programHead = User::factory()->create([
            'role' => 'program_head',
            'college_id' => $college->id,
            'program_id' => $program->id,
        ]);
        $date = now()->toDateString();

        Attendance::create(['student_id' => $student->id, 'date' => $date, 'status' => 'present', 'work_mode' => 'wfo', 'session_type' => 'full_day']);
        Attendance::create(['student_id' => $student->id, 'date' => now()->subMonth()->toDateString(), 'status' => 'absent', 'work_mode' => 'wfo', 'session_type' => 'full_day']);
        Attendance::create(['student_id' => $otherStudent->id, 'date' => $date, 'status' => 'absent', 'work_mode' => 'wfo', 'session_type' => 'full_day']);

        Sanctum::actingAs($programHead);

        $this->getJson("/api/program-head/reports?date_from={$date}&date_to={$date}")
            ->assertOk()
            ->assertJsonPath('overview.total_students', 1)
            ->assertJsonPath('attendance.total', 1)
            ->assertJsonPath('attendance.rate', 100)
            ->assertJsonPath('filters.date_from', $date)
            ->assertJsonPath('scope.program.id', $program->id);

        $this->getJson('/api/program-head/reports?date_from=2026-08-25&date_to=2026-08-24')
            ->assertUnprocessable();

        $preview = $this->getJson("/api/program-head/reports/preview?report=program_summary&date_from={$date}&date_to={$date}")
            ->assertOk()
            ->assertJsonPath('title', 'Program Internship Summary Report')
            ->assertJsonPath('summary.Total students', 1);

        $export = $this->get("/api/program-head/reports/export/pdf?report=program_summary&date_from={$date}&date_to={$date}")
            ->assertOk();
        $this->assertStringStartsWith('%PDF', $export->getContent());
        $this->assertNotEmpty($preview->json('headers'));
    }

    public function test_vpaa_report_filters_university_data_by_program(): void
    {
        [$college, $program] = $this->academicScope('CC', 'BSIT');
        [$otherCollege, $otherProgram] = $this->academicScope('CAS', 'BAPOLSCI');
        $this->student($college, $program, '2026-1001');
        $this->student($otherCollege, $otherProgram, '2026-2001');
        $vpaa = User::factory()->create(['role' => 'vpaa']);

        Sanctum::actingAs($vpaa);

        $this->getJson("/api/vpaa/reports?program_id={$program->id}")
            ->assertOk()
            ->assertJsonPath('overview.total_students', 1)
            ->assertJsonPath('analytics.program_performance.0.program', 'BSIT')
            ->assertJsonPath('filters.program_id', $program->id)
            ->assertJsonCount(2, 'scope.programs');
    }

    public function test_vpaa_can_download_real_csv_xlsx_and_pdf_report_files(): void
    {
        [$college, $program] = $this->academicScope('CC', 'BSIT');
        $this->student($college, $program, '2026-3001');
        Sanctum::actingAs(User::factory()->create(['role' => 'vpaa']));

        $expectations = [
            'csv' => ["\xEF\xBB\xBF", '.csv'],
            'xlsx' => ['PK', '.xlsx'],
            'pdf' => ['%PDF', '.pdf'],
        ];

        foreach ($expectations as $format => [$signature, $extension]) {
            $response = $this->get("/api/vpaa/reports/export/{$format}?report=overall_summary&program_id={$program->id}")
                ->assertOk();
            $this->assertStringContainsString($extension, (string) $response->headers->get('content-disposition'));

            if ($response->baseResponse instanceof BinaryFileResponse) {
                $path = $response->baseResponse->getFile()->getPathname();
                $content = file_get_contents($path);

                if ($format === 'xlsx') {
                    $reader = new Reader;
                    $reader->open($path);
                    $rows = [];

                    foreach ($reader->getSheetIterator() as $sheet) {
                        foreach ($sheet->getRowIterator() as $row) {
                            $rows[] = $row->toArray();
                        }
                    }

                    $reader->close();
                    $this->assertSame('Overall Internship Summary Report', $rows[0][0]);
                    $this->assertTrue(collect($rows)->contains(fn (array $row) => in_array('Program', $row, true)));
                    $this->assertTrue(collect($rows)->contains(fn (array $row) => ($row[0] ?? null) === 'BSIT'));
                }

                @unlink($path);
            } else {
                $content = $response->getContent();
            }

            $this->assertStringStartsWith($signature, $content);
        }
    }

    public function test_each_role_exposes_exactly_three_supported_preview_types(): void
    {
        [$college, $program] = $this->academicScope('CC', 'BSIT');
        $coordinator = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        $programHead = User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id]);
        $vpaa = User::factory()->create(['role' => 'vpaa']);

        foreach (['student_internship', 'hte_deployment', 'travel_monitoring'] as $report) {
            Sanctum::actingAs($coordinator);
            $this->getJson("/api/coordinator/reports/preview?report={$report}")->assertOk()->assertJsonStructure(['title', 'summary', 'headers', 'rows']);
        }
        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinator/reports/preview?report=attendance')->assertUnprocessable();

        foreach (['program_summary', 'hte_deployment_review', 'travel_monitoring_review'] as $report) {
            Sanctum::actingAs($programHead);
            $this->getJson("/api/program-head/reports/preview?report={$report}")->assertOk()->assertJsonStructure(['title', 'summary', 'headers', 'rows']);
        }
        Sanctum::actingAs($programHead);
        $this->getJson('/api/program-head/reports/preview?report=performance')->assertUnprocessable();

        foreach (['overall_summary', 'hte_deployment_validation', 'travel_monitoring_validation'] as $report) {
            Sanctum::actingAs($vpaa);
            $this->getJson("/api/vpaa/reports/preview?report={$report}")->assertOk()->assertJsonStructure(['title', 'summary', 'headers', 'rows']);
        }
        Sanctum::actingAs($vpaa);
        $this->getJson('/api/vpaa/reports/preview?report=executive')->assertUnprocessable();
    }

    private function academicScope(string $collegeCode, string $programCode): array
    {
        $college = College::create(['name' => $collegeCode.' College', 'code' => $collegeCode, 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => $programCode.' Program', 'code' => $programCode, 'is_active' => true]);

        return [$college, $program];
    }

    private function student(College $college, Program $program, string $schoolId): Student
    {
        $user = User::factory()->create(['role' => 'student']);
        $period = $this->academicPeriod();

        return Student::create([
            'user_id' => $user->id,
            'student_id' => $schoolId,
            'first_name' => 'Report',
            'last_name' => $schoolId,
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'contact_number' => '09170000000',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_period_id' => $period->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Parent',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09171111111',
            'registration_status' => 'approved',
            'internship_status' => 'active',
            'required_ojt_hours' => 486,
        ]);
    }
}
