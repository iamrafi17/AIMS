<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\College;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorSectionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_head_assigns_sections_and_coordinators_only_see_their_students(): void
    {
        $college = College::create(['name' => 'Business', 'code' => 'CBA', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Tourism', 'code' => 'BSTM', 'is_active' => true]);
        $period = AcademicPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'status' => 'active']);
        $sections = collect(['A', 'B', 'C', 'D'])->map(fn ($name) => AcademicSection::create([
            'program_id' => $program->id, 'academic_period_id' => $period->id,
            'name' => $name, 'year_level' => 4, 'academic_year' => '2026-2027', 'is_active' => true,
        ]));
        $head = User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id]);
        $one = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        $two = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $program->id]);
        $students = $sections->map(function ($section, $index) use ($college, $program) {
            $studentUser = User::factory()->create(['role' => 'student']);
            return Student::create([
                'user_id' => $studentUser->id, 'student_id' => 'BSTM-'.($index + 1),
                'first_name' => 'Student', 'last_name' => $section->name,
                'gender' => 'female', 'birth_date' => '2002-01-01',
                'address' => 'Santa Cruz', 'contact_number' => '09170000000',
                'college_id' => $college->id, 'program_id' => $program->id,
                'academic_section_id' => $section->id, 'year_level' => 4, 'section' => $section->name,
                'parent_name' => 'Parent', 'parent_address' => 'Santa Cruz',
                'parent_contact_number' => '09171111111',
                'registration_status' => 'approved', 'internship_status' => 'active',
            ]);
        });
        $otherAttendance = Attendance::create([
            'student_id' => $students[2]->id, 'date' => '2026-09-01',
            'status' => 'present', 'work_mode' => 'wfo', 'session_type' => 'full_day',
        ]);

        Sanctum::actingAs($one);
        $this->getJson('/api/coordinator/students')->assertOk()->assertJsonPath('summary.total', 0);

        Sanctum::actingAs($head);
        $this->getJson('/api/program-head/coordinator-assignments')
            ->assertOk()->assertJsonPath('coordinators.0.name', $one->name)
            ->assertJsonPath('coordinators.0.username', $one->username)
            ->assertJsonPath('periods.0.semester', '1st Semester')
            ->assertJsonPath('periods.0.academic_year', '2026-2027')
            ->assertJsonPath('sections.0.student_count', 1)
            ->assertJsonPath('sections.0.academic_period_id', $period->id);
        $this->putJson('/api/program-head/coordinator-assignments', [
            'assignments' => $sections->values()->map(fn ($section, $index) => [
                'section_id' => $section->id,
                'coordinator_id' => $index < 2 ? $one->id : $two->id,
            ])->all(),
        ])->assertOk();

        Sanctum::actingAs($one);
        $this->getJson('/api/coordinator/students')->assertOk()->assertJsonPath('summary.total', 2);
        $this->getJson('/api/coordinator/students/'.$students[2]->id)->assertNotFound();
        $this->getJson('/api/coordinator/attendance/'.$otherAttendance->id)->assertNotFound();
        $this->getJson('/api/coordinator/attendance')->assertOk()->assertJsonPath('summary.total', 0);

        Sanctum::actingAs($head);
        $this->putJson('/api/program-head/coordinator-assignments', [
            'assignments' => [['section_id' => $sections[2]->id, 'coordinator_id' => $one->id]],
        ])->assertOk();

        Sanctum::actingAs($one);
        $this->getJson('/api/coordinator/students')->assertOk()->assertJsonPath('summary.total', 3);
        $this->getJson('/api/coordinator/students/'.$students[2]->id)->assertOk();
        Sanctum::actingAs($two);
        $this->getJson('/api/coordinator/students')->assertOk()->assertJsonPath('summary.total', 1);

        Sanctum::actingAs($head);
        $this->putJson('/api/program-head/coordinator-assignments', [
            'assignments' => $sections->map(fn ($section) => [
                'section_id' => $section->id, 'coordinator_id' => $one->id,
            ])->all(),
        ])->assertOk();
        Sanctum::actingAs($one);
        $this->getJson('/api/coordinator/students')->assertOk()->assertJsonPath('summary.total', 4);
        Sanctum::actingAs($two);
        $this->getJson('/api/coordinator/students')->assertOk()->assertJsonPath('summary.total', 0);
    }

    public function test_program_head_cannot_assign_another_programs_section_or_coordinator(): void
    {
        $college = College::create(['name' => 'Business', 'code' => 'CBA', 'is_active' => true]);
        $ownProgram = Program::create(['college_id' => $college->id, 'name' => 'Tourism', 'code' => 'BSTM', 'is_active' => true]);
        $otherProgram = Program::create(['college_id' => $college->id, 'name' => 'Management', 'code' => 'BSBA', 'is_active' => true]);
        $period = AcademicPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'status' => 'active']);
        $ownSection = AcademicSection::create(['program_id' => $ownProgram->id, 'academic_period_id' => $period->id, 'name' => 'A', 'year_level' => 4, 'academic_year' => '2026-2027', 'is_active' => true]);
        $otherSection = AcademicSection::create(['program_id' => $otherProgram->id, 'academic_period_id' => $period->id, 'name' => 'A', 'year_level' => 4, 'academic_year' => '2026-2027', 'is_active' => true]);
        $head = User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $ownProgram->id]);
        $otherCoordinator = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id, 'program_id' => $otherProgram->id]);
        Sanctum::actingAs($head);

        $this->putJson('/api/program-head/coordinator-assignments', ['assignments' => [
            ['section_id' => $otherSection->id, 'coordinator_id' => $otherCoordinator->id],
        ]])->assertUnprocessable();
        $this->putJson('/api/program-head/coordinator-assignments', ['assignments' => [
            ['section_id' => $ownSection->id, 'coordinator_id' => $otherCoordinator->id],
        ]])->assertUnprocessable();
        $this->assertDatabaseCount('coordinator_section_assignments', 0);
    }
}
