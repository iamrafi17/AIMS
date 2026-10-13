<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\College;
use App\Models\HTE;
use App\Models\HteDepartment;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\SecurityQuestion;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_hte_and_department_choices_are_saved_as_preferences_without_deploying_the_student(): void
    {
        /** @var User $coordinator */
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'status' => 'active',
        ]);
        $section = AcademicSection::create([
            'academic_period_id' => $period->id, 'program_id' => $program->id,
            'name' => '4A', 'year_level' => 4, 'academic_year' => $period->academic_year, 'is_active' => true,
        ]);
        OjtEnrollment::create([
            'school_id' => '2026-PREF-001', 'full_name' => 'SANTOS, Maria Dela',
            'section' => 'BSIT 4A', 'college_id' => $college->id,
            'program_id' => $program->id, 'academic_section_id' => $section->id,
            'source' => 'manual', 'added_by' => $coordinator->id,
        ]);
        $hte = HTE::create([
            'name' => 'Partner Company', 'address' => 'Santa Cruz',
            'contact_person' => 'Supervisor', 'contact_email' => 'supervisor@example.com',
            'contact_number' => '09170000000', 'is_active' => true,
        ]);
        $hte->programs()->attach($program->id, ['linked_by' => $coordinator->id, 'is_active' => true]);
        $department = HteDepartment::create(['hte_id' => $hte->id, 'department_name' => 'IT Office', 'normalized_name' => 'it office']);
        $department->programs()->attach($program->id, ['linked_by' => $coordinator->id, 'is_active' => true]);
        $unavailableDepartment = HteDepartment::create(['hte_id' => $hte->id, 'department_name' => 'Finance Office', 'normalized_name' => 'finance office']);
        $unavailableDepartment->programs()->attach($program->id, ['linked_by' => $coordinator->id, 'is_active' => false]);

        $this->getJson("/api/colleges/{$college->id}/htes?program_id={$program->id}")
            ->assertOk()
            ->assertJsonPath('0.departments.0.id', $department->id)
            ->assertJsonMissing(['department_name' => 'Finance Office']);

        $payload = [
            ...$this->registrationPayload('2026-PREF-001'),
            'hte_id' => $hte->id,
            'hte_department_id' => $unavailableDepartment->id,
        ];
        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hte_department_id');

        $response = $this->postJson('/api/register', [
            ...$payload,
            'hte_department_id' => $department->id,
        ])->assertCreated();

        $this->assertDatabaseHas('student_internships', [
            'student_id' => $response->json('student.id'),
            'preferred_hte_id' => $hte->id,
            'preferred_hte_department_id' => $department->id,
        ]);
        $this->assertDatabaseMissing('students', ['id' => $response->json('student.id'), 'hte_id' => $hte->id]);
        $this->assertDatabaseCount('student_internship_placements', 0);

        $coordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        DB::table('coordinator_section_assignments')->insert([
            'academic_section_id' => $section->id,
            'coordinator_id' => $coordinator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($coordinator)->getJson('/api/coordinator/htes')
            ->assertOk()
            ->assertJsonPath('deployments.0.current_internship.preferred_hte.name', $hte->name)
            ->assertJsonPath('deployments.0.current_internship.preferred_department.department_name', $department->department_name);
    }

    #[DataProvider('registrationYearLevels')]
    public function test_enrolled_student_creates_their_own_credentials_during_registration(int $yearLevel, ?int $submittedYearLevel, string $source): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'required_ojt_hours' => 600, 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $period = AcademicPeriod::create(['academic_year' => '2026-2027', 'semester' => '1st Semester', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'status' => 'draft']);
        AcademicPeriod::create(['academic_year' => '2027-2028', 'semester' => '2nd Semester', 'start_date' => '2028-01-01', 'end_date' => '2028-05-31', 'status' => 'active']);
        $section = AcademicSection::create([
            'academic_period_id' => $period->id,
            'program_id' => $program->id,
            'name' => "{$yearLevel}A",
            'year_level' => $yearLevel,
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $coordinator->update(['college_id' => $college->id, 'program_id' => $program->id]);
        OjtEnrollment::create([
            'school_id' => '2026-REG-001',
            'full_name' => 'SANTOS, Maria Dela',
            'section' => "BSIT {$yearLevel}A",
            'college_id' => $college->id,
            'program_id' => $program->id,
            'academic_section_id' => $section->id,
            'source' => $source,
            'added_by' => $coordinator->id,
        ]);
        $this->getJson('/api/registration/enrollment/2026-REG-001')
            ->assertOk()
            ->assertJsonPath('full_name', 'SANTOS, Maria Dela')
            ->assertJsonPath('first_name', 'Maria Dela')
            ->assertJsonPath('last_name', 'Santos')
            ->assertJsonPath('section', "{$yearLevel}A")
            ->assertJsonPath('academic_section.id', $section->id)
            ->assertJsonPath('academic_section.year_level', $yearLevel)
            ->assertJsonPath('academic_period.id', $period->id)
            ->assertJsonPath('internship_semester', 'First Semester')
            ->assertJsonPath('internship_year', '2026-2027')
            ->assertJsonPath('required_ojt_hours', 600);

        $payload = [
            ...$this->registrationPayload('2026-REG-001'),
            'college_id' => $college->id,
            'program_id' => $program->id,
            'internship_semester' => 'Second Semester',
            'internship_year' => '2030-2031',
            'academic_period_id' => 999999,
        ];
        if ($submittedYearLevel !== null) {
            $payload['year_level'] = $submittedYearLevel;
        } else {
            unset($payload['internship_semester'], $payload['internship_year']);
        }

        $this->postJson('/api/register', [
            ...$payload,
            'contact_number' => '0917123456',
            'parent_contact_number' => 'not-a-number',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_number', 'parent_contact_number']);

        $response = $this->postJson('/api/register', $payload)
            ->assertCreated()
            ->assertJsonPath('user.email', 'maria.santos@student.marsu.edu.ph')
            ->assertJsonPath('student.student_id', '2026-REG-001');

        $this->assertDatabaseHas('students', [
            'id' => $response->json('student.id'),
            'student_id' => '2026-REG-001',
            'first_name' => 'Maria Dela',
            'middle_name' => 'Reyes',
            'last_name' => 'Santos',
        ]);
        $this->assertDatabaseHas('ojt_enrollments', [
            'school_id' => '2026-REG-001',
            'student_record_id' => $response->json('student.id'),
            'academic_section_id' => $section->id,
            'registration_status' => 'approved',
            'registration_reviewed_by' => null,
            'registration_reviewed_at' => null,
        ]);
        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $response->json('student.id'),
            'full_name' => 'Parent Santos',
            'relationship' => 'Mother',
        ]);
        $this->assertDatabaseHas('student_internships', [
            'student_id' => $response->json('student.id'),
            'academic_section_id' => $section->id,
            'required_hours' => 600,
            'status' => 'pending',
            'is_current' => true,
        ]);

        foreach (['2026-REG-001', $payload['email']] as $login) {
            $this->postJson('/api/login', [
                'login' => $login,
                'password' => $payload['password'],
                'device_name' => 'registration-test',
            ])->assertOk()->assertJsonPath('user.role', 'student')->assertJsonStructure(['token']);
        }

        // Accounts from the former workflow no longer wait on registration review.
        Student::findOrFail($response->json('student.id'))->update(['registration_status' => 'pending']);
        $this->postJson('/api/login', [
            'login' => '2026-REG-001',
            'password' => $payload['password'],
            'device_name' => 'legacy-registration-test',
        ])->assertOk();

        $payload['email'] = 'another@student.marsu.edu.ph';
        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    public function test_unlisted_school_id_cannot_register(): void
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);

        $this->getJson('/api/registration/enrollment/NOT-LISTED')
            ->assertNotFound()
            ->assertJsonPath(
                'message',
                'Your School No. was not found in the official student list.',
            );

        $this->postJson('/api/register', [
            'student_id' => 'NOT-LISTED',
            'first_name' => 'Not',
            'last_name' => 'Listed',
            'gender' => 'male',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz',
            'contact_number' => '09170000000',
            'email' => 'not.listed@example.com',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'year_level' => 4,
            'section' => 'A',
            'parent_name' => 'Parent',
            'parent_relationship' => 'Father',
            'parent_address' => 'Santa Cruz',
            'parent_contact_number' => '09171111111',
            'hte_id' => null,
            'internship_semester' => 'First Semester',
            'internship_year' => '2026-2027',
            'agree_terms' => true,
            'agree_privacy' => true,
            'password' => 'Student1234!',
            'password_confirmation' => 'Student1234!',
            'security_questions' => $this->securityQuestionAnswers(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('student_id')
            ->assertJsonPath(
                'errors.student_id.0',
                'Your School No. was not found in the official student list.',
            );
    }

    public static function registrationYearLevels(): array
    {
        return [
            'submitted year cannot override official year' => [4, 5, 'csv'],
            'third year needs no submitted year' => [3, null, 'manual'],
            'sixth year ignores arbitrary submitted year' => [6, 999, 'csv'],
        ];
    }

    public function test_enrollment_without_a_linked_section_is_rejected_before_it_can_be_saved(): void
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An OJT enrollment requires an academic section.');

        OjtEnrollment::create([
            'school_id' => '2026-NO-SECTION',
            'full_name' => 'SANTOS, Maria Dela',
            'section' => 'BSIT 4A',
            'college_id' => $college->id,
            'program_id' => $program->id,
            'source' => 'manual',
        ]);

    }

    public function test_database_rejects_an_academic_section_without_an_official_period(): void
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);

        $this->expectException(QueryException::class);
        AcademicSection::create([
            'program_id' => $program->id, 'name' => '4A', 'year_level' => 4,
            'academic_year' => '2026-2027', 'is_active' => true,
        ]);
    }

    #[DataProvider('registrationPasswords')]
    public function test_registration_enforces_password_length_and_confirmation_without_consuming_invalid_enrollments(string $password, string $confirmation, bool $accepted): void
    {
        $college = College::create(['name' => 'Computing', 'code' => 'CICS', 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true]);
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027', 'semester' => '1st Semester',
            'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'status' => 'active',
        ]);
        $section = AcademicSection::create([
            'academic_period_id' => $period->id, 'program_id' => $program->id,
            'name' => '4A', 'year_level' => 4, 'academic_year' => $period->academic_year, 'is_active' => true,
        ]);
        $enrollment = OjtEnrollment::create([
            'school_id' => '2026-PASSWORD-001', 'full_name' => 'SANTOS, Maria Dela',
            'section' => 'BSIT 4A', 'college_id' => $college->id,
            'program_id' => $program->id, 'academic_section_id' => $section->id,
            'source' => 'manual',
        ]);

        $response = $this->postJson('/api/register', [
            ...$this->registrationPayload($enrollment->school_id),
            'password' => $password,
            'password_confirmation' => $confirmation,
        ]);

        if (! $accepted) {
            $response->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->assertDatabaseMissing('users', ['email' => 'maria.santos@student.marsu.edu.ph']);
            $this->assertDatabaseMissing('students', ['student_id' => $enrollment->school_id]);
            $this->assertNull($enrollment->fresh()->registered_at);
            $this->assertNull($enrollment->fresh()->student_record_id);
            $this->assertDatabaseCount('user_security_questions', 0);

            return;
        }

        $response->assertCreated();
        $user = User::findOrFail($response->json('user.id'));
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertFalse(Hash::check(strtolower($password), $user->password));
        $this->assertNotNull($enrollment->fresh()->registered_at);
        $this->assertDatabaseCount('user_security_questions', 3);
    }

    public static function registrationPasswords(): array
    {
        return [
            'seven characters rejected' => ['Short1!', 'Short1!', false],
            'eight characters accepted' => ['NewPass1', 'NewPass1', true],
            'twelve characters accepted' => ['Student1234!', 'Student1234!', true],
            'thirteen characters rejected' => ['Student1234!x', 'Student1234!x', false],
            'different confirmation case rejected' => ['Student1234!', 'student1234!', false],
        ];
    }

    private function registrationPayload(string $studentId): array
    {
        return [
            'student_id' => $studentId,
            'first_name' => 'Tampered',
            'last_name' => 'Name',
            'middle_name' => 'Reyes',
            'gender' => 'female',
            'birth_date' => '2002-01-01',
            'address' => 'Santa Cruz, Marinduque',
            'contact_number' => '09171234567',
            'email' => 'maria.santos@student.marsu.edu.ph',
            'section' => 'Wrong Section',
            'parent_name' => 'Parent Santos',
            'parent_relationship' => 'Mother',
            'parent_address' => 'Santa Cruz, Marinduque',
            'parent_contact_number' => '09179876543',
            'hte_id' => null,
            'internship_semester' => 'First Semester',
            'internship_year' => '2026-2027',
            'agree_terms' => true,
            'agree_privacy' => true,
            'password' => 'Student1234!',
            'password_confirmation' => 'Student1234!',
            'security_questions' => $this->securityQuestionAnswers(),
        ];
    }

    private function securityQuestionAnswers(): array
    {
        return SecurityQuestion::query()->take(3)->get()->map(
            fn (SecurityQuestion $question, int $index) => [
                'security_question_id' => $question->id,
                'answer' => ['Adobo', 'Volleyball', 'Reading'][$index],
            ],
        )->all();
    }
}
