<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\College;
use App\Models\HTE;
use App\Models\HteDepartment;
use App\Models\HteDepartmentProgram;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentInternshipPlacement;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (! preg_match('/^aims_evaluation_verify_\d{14}$/', $db->getDatabaseName())) {
    throw new RuntimeException('Browser fixtures must only use the isolated verification database.');
}
$college = College::create(['name' => 'Evaluation QA College', 'code' => 'QA', 'is_active' => true]);
$program = Program::create(['name' => 'Information Systems', 'code' => 'QAIS', 'college_id' => $college->id, 'is_active' => true]);
$period = AcademicPeriod::create(['academic_year' => '2090-2091', 'semester' => '2nd Semester', 'start_date' => '2091-01-01', 'end_date' => '2091-05-31', 'status' => 'draft']);
$section = AcademicSection::create(['program_id' => $program->id, 'academic_period_id' => $period->id, 'name' => 'QA 4B', 'year_level' => 4, 'is_active' => true]);
$coordinator = User::factory()->create(['role' => 'coordinator', 'program_id' => $program->id, 'college_id' => $college->id]);
$db->table('coordinator_section_assignments')->insert(['coordinator_id' => $coordinator->id, 'academic_section_id' => $section->id, 'created_at' => now(), 'updated_at' => now()]);
$supervisor = User::factory()->create(['role' => 'supervisor']);
$hte = HTE::create(['name' => 'QA Training Office', 'address' => 'QA only', 'contact_person' => 'QA Head', 'contact_email' => 'qa@example.test', 'contact_number' => '09170000000', 'is_active' => true]);
$department = HteDepartment::create(['hte_id' => $hte->id, 'department_name' => 'IT', 'normalized_name' => 'it']);
$link = HteDepartmentProgram::create(['hte_department_id' => $department->id, 'program_id' => $program->id, 'is_active' => true]);
$assignment = Supervisor::create(['hte_department_id' => $department->id, 'full_name' => 'QA Supervisor', 'user_id' => $supervisor->id, 'is_active' => true]);
$supervisor->refresh()->update(['profile_completed_at' => now()]);
$student = Student::create(['user_id' => User::factory()->create(['role' => 'student'])->id, 'student_id' => 'QA-2090-0001', 'first_name' => 'QA', 'last_name' => 'Intern', 'academic_section_id' => $section->id, 'program_id' => $program->id, 'college_id' => $college->id, 'academic_period_id' => $period->id, 'year_level' => 4, 'section' => 'QA 4B', 'registration_status' => 'approved']);
$internship = $student->currentInternship;
StudentInternshipPlacement::create(['student_internship_id' => $internship->id, 'hte_department_program_id' => $link->id, 'supervisor_record_id' => $assignment->id, 'is_current' => true]);
$head = User::factory()->create(['role' => 'program_head', 'college_id' => $college->id, 'program_id' => $program->id]);
$dean = User::factory()->create(['role' => 'dean', 'college_id' => $college->id]);
$tokens = [];
foreach (['coordinator' => $coordinator, 'supervisor' => $supervisor, 'program_head' => $head, 'dean' => $dean] as $role => $user) {
    $tokens[$role] = $user->createToken('evaluation-browser-qa')->plainTextToken;
}
file_put_contents(storage_path('app/private/evaluation-browser-fixtures.json'), json_encode(['tokens' => $tokens, 'period_id' => $period->id, 'internship_id' => $internship->id]));
echo 'Browser fixtures created only in '.$db->getDatabaseName().PHP_EOL;
