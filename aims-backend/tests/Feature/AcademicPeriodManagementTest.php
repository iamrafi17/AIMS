<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\College;
use App\Models\GeneratedReport;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\ReportTemplate;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicPeriodManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_list_edit_and_delete_an_unlinked_period(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $id = $this->postJson('/api/admin/academic-periods', $this->payload(['academic_year' => '2026–2027']))
            ->assertCreated()->assertJsonPath('period.academic_year', '2026-2027')
            ->assertJsonPath('period.semester', '2nd Semester')->json('period.id');

        $this->getJson('/api/admin/academic-periods')->assertOk()
            ->assertJsonPath('periods.0.id', $id)->assertJsonPath('periods.0.students_count', 0)
            ->assertJsonPath('periods.0.generated_reports_count', 0);
        $this->putJson('/api/admin/academic-periods/'.$id, $this->payload(['start_date' => '2026-12-16']))
            ->assertOk()->assertJsonPath('period.start_date', '2026-12-16');
        $this->deleteJson('/api/admin/academic-periods/'.$id)->assertOk();
        $this->assertDatabaseMissing('academic_periods', ['id' => $id]);
        $this->assertDatabaseHas('activity_logs', ['activity_type' => 'Academic Period', 'description' => 'Deleted an unlinked academic period.']);
    }

    public function test_creating_editing_and_activating_periods_never_leaves_two_active_periods(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $first = $this->postJson('/api/admin/academic-periods', $this->payload(['semester' => '1st Semester', 'status' => 'active']))
            ->assertCreated()->json('period.id');
        $second = $this->postJson('/api/admin/academic-periods', $this->payload(['status' => 'active']))
            ->assertCreated()->json('period.id');
        $this->assertSame(1, AcademicPeriod::where('status', 'active')->count());
        $this->assertDatabaseHas('academic_periods', ['id' => $first, 'status' => 'draft']);

        $this->putJson('/api/admin/academic-periods/'.$first.'/activate')->assertOk()->assertJsonPath('period.status', 'active');
        $this->assertDatabaseHas('academic_periods', ['id' => $second, 'status' => 'draft']);
        $this->putJson('/api/admin/academic-periods/'.$second, $this->payload(['status' => 'active']))->assertOk();
        $this->assertSame(1, AcademicPeriod::where('status', 'active')->count());
        $this->assertDatabaseHas('academic_periods', ['id' => $first, 'status' => 'draft']);
        $this->assertDatabaseHas('academic_periods', ['id' => $second, 'status' => 'active']);
    }

    public function test_database_also_enforces_the_single_active_period_rule(): void
    {
        AcademicPeriod::create($this->payload(['status' => 'active']));
        $this->expectException(QueryException::class);
        AcademicPeriod::create($this->payload(['semester' => '1st Semester', 'status' => 'active']));
    }

    public function test_invalid_periods_and_duplicate_terms_do_not_change_the_active_period(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $active = $this->postJson('/api/admin/academic-periods', $this->payload(['status' => 'active']))->assertCreated()->json('period.id');
        foreach ([
            ['end_date' => '2026-12-14'],
            ['academic_year' => '2026-2028'],
            ['semester' => 'Invalid Semester'],
            ['academic_year' => ['2026', '2027']],
            ['semester' => ['Second Semester']],
            ['start_date' => '2025-12-15'],
            ['end_date' => '2028-04-25'],
            ['status' => 'completed'],
        ] as $invalid) {
            $this->postJson('/api/admin/academic-periods', $this->payload($invalid))->assertUnprocessable();
        }
        $this->postJson('/api/admin/academic-periods', $this->payload(['semester' => 'Second Semester', 'status' => 'active']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_year');
        $this->assertDatabaseCount('academic_periods', 1);
        $this->assertDatabaseHas('academic_periods', ['id' => $active, 'status' => 'active']);
    }

    public function test_completion_archives_the_period_preserves_students_and_prevents_reactivation(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $period = AcademicPeriod::create($this->payload(['status' => 'active']));
        $student = $this->student();
        $this->assertSame($period->id, $student->academic_period_id);
        $this->putJson('/api/admin/academic-periods/'.$period->id.'/complete')->assertOk()
            ->assertJsonPath('period.status', 'completed');
        $completedAt = $period->fresh()->completed_at;
        $this->assertNotNull($completedAt);
        $this->assertSame($period->id, $student->ojtEnrollments()->firstOrFail()->academic_period_id);
        $this->assertSame(0, AcademicPeriod::where('status', 'active')->count());
        $this->putJson('/api/admin/academic-periods/'.$period->id.'/activate')->assertUnprocessable();
        $this->putJson('/api/admin/academic-periods/'.$period->id, $this->payload(['status' => 'active']))->assertUnprocessable();
        $this->putJson('/api/admin/academic-periods/'.$period->id.'/complete')->assertOk();
        $this->assertTrue($completedAt->equalTo($period->fresh()->completed_at));
        $this->deleteJson('/api/admin/academic-periods/'.$period->id)->assertUnprocessable();
    }

    public function test_existing_students_are_linked_on_creation_and_deletion_is_blocked(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $periodId = $this->postJson('/api/admin/academic-periods', $this->payload())->assertCreated()->json('period.id');
        $student = $this->student(['internship_year' => '2026–2027']);
        $this->assertSame($periodId, $student->academic_period_id);
        $this->deleteJson('/api/admin/academic-periods/'.$periodId)->assertUnprocessable();
        $this->getJson('/api/admin/academic-periods')->assertOk()->assertJsonPath('periods.0.students_count', 1);
    }

    public function test_year_and_semester_corrections_keep_links_and_student_filters_consistent(): void
    {
        $period = AcademicPeriod::create($this->payload());
        $student = $this->student();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/admin/academic-periods/'.$period->id, $this->payload([
            'academic_year' => '2027-2028', 'semester' => '1st Semester',
            'start_date' => '2027-08-01', 'end_date' => '2027-12-01',
        ]))->assertOk();
        $this->assertSame($period->id, $student->ojtEnrollments()->firstOrFail()->academic_period_id);
        $student = $student->fresh();
        $this->assertSame('2027-2028', $student->internship_year);
        $this->assertSame('First Semester', $student->internship_semester);
        $this->deleteJson('/api/admin/academic-periods/'.$period->id)->assertUnprocessable();
    }

    public function test_sections_and_enrollment_imports_are_linked_and_protect_the_period(): void
    {
        $period = AcademicPeriod::create($this->payload(['status' => 'active']));
        $college = College::create(['name' => 'Computing', 'code' => 'CICS']);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Systems', 'code' => 'BSIS']);
        $section = AcademicSection::create(['program_id' => $program->id, 'name' => '4A', 'year_level' => 4, 'academic_year' => '2026-2027']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        Sanctum::actingAs($coordinator);
        $enrollmentId = $this->postJson('/api/coordinator/students', [
            'school_id' => 'PERIOD-001', 'full_name' => 'Student Period', 'academic_section_id' => $section->id,
        ])->assertCreated()->assertJsonPath('enrollment.academic_period_id', $period->id)->json('enrollment.id');
        $this->assertSame($period->id, $section->academic_period_id);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson('/api/admin/academic-periods/'.$period->id)->assertUnprocessable();
        $this->putJson('/api/admin/academic-periods/'.$period->id.'/complete')->assertOk();
        $this->assertSame($period->id, OjtEnrollment::findOrFail($enrollmentId)->academic_period_id);
    }

    public function test_soft_deleted_templates_and_generated_reports_remain_linked_to_the_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $period = AcademicPeriod::create($this->payload());
        $template = ReportTemplate::create([
            'report_type' => 'student_progress', 'name' => 'Period report', 'original_filename' => 'report.docx',
            'checksum' => str_repeat('a', 64), 'period_scope' => ReportTemplate::SCOPE_ACADEMIC_PERIOD,
            'academic_period_id' => $period->id, 'uploaded_by' => $admin->id,
        ]);
        GeneratedReport::create([
            'report_template_id' => $template->id, 'report_type' => 'student_progress', 'file_path' => 'report.pdf',
            'filename' => 'report.pdf', 'generated_by' => $admin->id,
        ]);
        $template->delete();
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/academic-periods')->assertOk()
            ->assertJsonPath('periods.0.report_templates_count', 1)->assertJsonPath('periods.0.generated_reports_count', 1);
        $this->deleteJson('/api/admin/academic-periods/'.$period->id)->assertUnprocessable();
    }

    public function test_foreign_keys_prevent_bypassing_delete_protection(): void
    {
        $period = AcademicPeriod::create($this->payload());
        $this->student();
        $this->expectException(QueryException::class);
        DB::table('academic_periods')->where('id', $period->id)->delete();
    }

    public function test_non_admin_users_cannot_manage_academic_periods(): void
    {
        $period = AcademicPeriod::create($this->payload());
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/admin/academic-periods')->assertForbidden();
        $this->postJson('/api/admin/academic-periods', $this->payload())->assertForbidden();
        $this->putJson('/api/admin/academic-periods/'.$period->id, $this->payload())->assertForbidden();
        $this->putJson('/api/admin/academic-periods/'.$period->id.'/activate')->assertForbidden();
        $this->putJson('/api/admin/academic-periods/'.$period->id.'/complete')->assertForbidden();
        $this->deleteJson('/api/admin/academic-periods/'.$period->id)->assertForbidden();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'academic_year' => '2026-2027', 'semester' => '2nd Semester',
            'start_date' => '2026-12-15', 'end_date' => '2027-04-25', 'status' => 'draft',
        ], $overrides);
    }

    private function student(array $overrides = []): Student
    {
        $year = AcademicPeriod::normalizeYear((string) ($overrides['internship_year'] ?? '2026-2027'));
        $semester = AcademicPeriod::normalizeSemester((string) ($overrides['internship_semester'] ?? 'Second Semester'));
        $period = AcademicPeriod::where('academic_year', $year)->where('semester', $semester)->firstOrFail();
        $college = College::firstOrCreate(['code' => 'CICS'], ['name' => 'Computing']);
        $program = Program::firstOrCreate(['code' => 'BSIS'], [
            'college_id' => $college->id,
            'name' => 'Information Systems',
        ]);
        $section = AcademicSection::firstOrCreate([
            'program_id' => $program->id,
            'academic_period_id' => $period->id,
            'name' => '4A',
        ], [
            'year_level' => 4,
            'academic_year' => $period->academic_year,
            'is_active' => true,
        ]);

        return Student::create(array_replace([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'student_id' => 'PERIOD-STUDENT', 'first_name' => 'Student', 'last_name' => 'Period', 'section' => '4A',
            'academic_section_id' => $section->id,
            'internship_year' => '2026-2027', 'internship_semester' => 'Second Semester',
        ], $overrides));
    }
}
