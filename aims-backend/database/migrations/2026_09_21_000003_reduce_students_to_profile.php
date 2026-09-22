<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NORMALIZED_COLUMNS = [
        'college_id', 'program_id', 'academic_section_id', 'academic_period_id',
        'year_level', 'section',
        'parent_name', 'parent_relationship', 'parent_address', 'parent_contact_number',
        'hte_id', 'hte_department_id', 'supervisor_record_id', 'supervisor_id',
        'internship_semester', 'internship_year', 'internship_status',
        'registration_status', 'registration_feedback', 'registration_reviewed_by', 'registration_reviewed_at',
        'consent_status', 'schedule_status', 'ojt_start_date', 'ojt_end_date',
        'required_ojt_hours', 'allow_past_attendance',
        'official_am_start', 'official_am_end', 'official_pm_start', 'official_pm_end', 'work_days',
    ];

    public function up(): void
    {
        $this->assertNormalizedBackfillIsComplete();

        Schema::table('students', function (Blueprint $table) {
            foreach ([
                'college_id', 'program_id', 'academic_section_id', 'academic_period_id',
                'hte_id', 'hte_department_id', 'supervisor_record_id', 'supervisor_id',
                'registration_reviewed_by',
            ] as $foreignKey) {
                $table->dropForeign([$foreignKey]);
            }

            $table->dropIndex('students_program_status_name_index');
            $table->dropIndex('students_program_registration_index');
            $table->dropIndex('students_program_hte_index');
            $table->dropIndex('students_supervisor_status_index');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(self::NORMALIZED_COLUMNS);
        });
    }

    private function assertNormalizedBackfillIsComplete(): void
    {
        $missingGuardian = DB::table('students as students')
            ->leftJoin('student_guardians as guardians', 'guardians.student_id', '=', 'students.id')
            ->where(function ($query) {
                $query->whereNotNull('students.parent_name')
                    ->orWhereNotNull('students.parent_relationship')
                    ->orWhereNotNull('students.parent_address')
                    ->orWhereNotNull('students.parent_contact_number');
            })
            ->whereNull('guardians.id')
            ->exists();

        $missingInternship = DB::table('students as students')
            ->leftJoin('student_internships as internships', function ($join) {
                $join->on('internships.student_id', '=', 'students.id')->where('internships.is_current', true);
            })
            ->whereNull('internships.id')
            ->exists();

        $missingEnrollment = DB::table('students as students')
            ->leftJoin('ojt_enrollments as enrollments', 'enrollments.student_record_id', '=', 'students.id')
            ->whereNull('enrollments.id')
            ->exists();

        if ($missingGuardian || $missingInternship || $missingEnrollment) {
            throw new RuntimeException('Student normalization backfill is incomplete; legacy columns were not removed.');
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_section_id')->nullable()->constrained('academic_sections')->nullOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->restrictOnDelete();
            $table->unsignedInteger('year_level')->nullable();
            $table->string('section', 50)->nullable();
            $table->string('parent_name')->nullable();
            $table->string('parent_relationship', 50)->nullable();
            $table->text('parent_address')->nullable();
            $table->string('parent_contact_number', 20)->nullable();
            $table->foreignId('hte_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hte_department_id')->nullable()->constrained('hte_departments')->nullOnDelete();
            $table->foreignId('supervisor_record_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('internship_semester', 30)->nullable();
            $table->string('internship_year', 20)->nullable();
            $table->string('internship_status', 20)->default('pending');
            $table->string('registration_status', 20)->default('pending');
            $table->text('registration_feedback')->nullable();
            $table->foreignId('registration_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registration_reviewed_at')->nullable();
            $table->string('consent_status', 20)->default('pending');
            $table->string('schedule_status', 20)->default('pending');
            $table->date('ojt_start_date')->nullable();
            $table->date('ojt_end_date')->nullable();
            $table->decimal('required_ojt_hours', 8, 2)->default(486);
            $table->boolean('allow_past_attendance')->default(false);
            $table->time('official_am_start')->nullable();
            $table->time('official_am_end')->nullable();
            $table->time('official_pm_start')->nullable();
            $table->time('official_pm_end')->nullable();
            $table->json('work_days')->nullable();

            $table->index(['program_id', 'internship_status', 'last_name'], 'students_program_status_name_index');
            $table->index(['program_id', 'registration_status'], 'students_program_registration_index');
            $table->index(['program_id', 'hte_id'], 'students_program_hte_index');
            $table->index(['supervisor_id', 'internship_status'], 'students_supervisor_status_index');
        });

        $this->restoreLegacyValues();
    }

    private function restoreLegacyValues(): void
    {
        DB::table('students')->orderBy('id')->get(['id'])->each(function (object $student): void {
            $guardian = DB::table('student_guardians')->where('student_id', $student->id)->first();
            $internship = DB::table('student_internships')
                ->where('student_id', $student->id)->where('is_current', true)->latest('id')->first();
            $enrollment = DB::table('ojt_enrollments')
                ->where('student_record_id', $student->id)->latest('registered_at')->latest('id')->first();
            $section = $internship?->academic_section_id
                ? DB::table('academic_sections')->where('id', $internship->academic_section_id)->first()
                : null;
            $program = $section?->program_id
                ? DB::table('programs')->where('id', $section->program_id)->first()
                : null;
            $periodId = $section?->academic_period_id ?: $enrollment?->academic_period_id;
            $period = $periodId ? DB::table('academic_periods')->where('id', $periodId)->first() : null;

            DB::table('students')->where('id', $student->id)->update([
                'college_id' => $program?->college_id,
                'program_id' => $program?->id,
                'academic_section_id' => $section?->id,
                'academic_period_id' => $period?->id,
                'year_level' => $section?->year_level,
                'section' => $section?->name,
                'parent_name' => $guardian?->full_name,
                'parent_relationship' => $guardian?->relationship,
                'parent_address' => $guardian?->address,
                'parent_contact_number' => $guardian?->contact_number,
                'hte_id' => $internship?->hte_id,
                'hte_department_id' => $internship?->hte_department_id,
                'supervisor_record_id' => $internship?->supervisor_record_id,
                'internship_semester' => $period?->semester,
                'internship_year' => $period?->academic_year,
                'internship_status' => $internship?->status ?? 'pending',
                'registration_status' => $enrollment?->registration_status ?? 'pending',
                'registration_feedback' => $enrollment?->registration_feedback,
                'registration_reviewed_by' => $enrollment?->registration_reviewed_by,
                'registration_reviewed_at' => $enrollment?->registration_reviewed_at,
                'consent_status' => $internship?->consent_status ?? 'pending',
                'schedule_status' => $internship?->deployment_status ?? 'pending',
                'ojt_start_date' => $internship?->start_date,
                'ojt_end_date' => $internship?->end_date,
                'required_ojt_hours' => $internship?->required_hours ?? 486,
                'allow_past_attendance' => $internship?->allow_past_attendance ?? false,
            ]);
        });
    }
};
