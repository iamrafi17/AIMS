<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function up(): void
    {
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('student_internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('ojt_enrollment_id')->nullable()->unique()->constrained('ojt_enrollments')->nullOnDelete();
            $table->foreignId('academic_section_id')->nullable()->constrained('academic_sections')->restrictOnDelete();
            $table->foreignId('hte_id')->nullable()->constrained('htes')->nullOnDelete();
            $table->foreignId('hte_department_id')->nullable()->constrained('hte_departments')->nullOnDelete();
            $table->foreignId('hte_department_program_id')->nullable()->constrained('hte_department_programs')->nullOnDelete();
            $table->foreignId('supervisor_record_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('consent_status', 20)->default('pending');
            $table->string('deployment_status', 20)->default('pending');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('required_hours', 8, 2)->default(486);
            $table->boolean('allow_past_attendance')->default(false);
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['student_id', 'academic_section_id'], 'student_internship_section_unique');
            $table->index(['student_id', 'is_current'], 'student_internship_current_index');
            $table->index(['status', 'deployment_status'], 'student_internship_status_index');
        });

        Schema::create('student_schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_internship_id')->constrained('student_internships')->cascadeOnDelete();
            $table->string('day_of_week', 3);
            $table->boolean('is_working')->default(true);
            $table->time('am_start')->nullable();
            $table->time('am_end')->nullable();
            $table->time('pm_start')->nullable();
            $table->time('pm_end')->nullable();
            $table->string('reason')->nullable();
            $table->string('source', 30)->default('legacy_sync');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_internship_id', 'day_of_week'], 'student_schedule_override_day_unique');
        });

        $this->backfillExistingStudents();
    }

    private function backfillExistingStudents(): void
    {
        DB::table('students')->orderBy('id')->get()->each(function (object $student): void {
            if ($student->parent_name || $student->parent_relationship || $student->parent_address || $student->parent_contact_number) {
                DB::table('student_guardians')->insert([
                    'student_id' => $student->id,
                    'full_name' => $student->parent_name ?: 'Not provided',
                    'relationship' => $student->parent_relationship,
                    'address' => $student->parent_address,
                    'contact_number' => $student->parent_contact_number,
                    'created_at' => $student->created_at ?? now(),
                    'updated_at' => $student->updated_at ?? now(),
                ]);
            }

            $enrollment = DB::table('ojt_enrollments')
                ->where('student_record_id', $student->id)
                ->orWhere(function ($query) use ($student): void {
                    $query->whereNull('student_record_id')->where('school_id', $student->student_id);
                })
                ->orderByDesc('registered_at')
                ->orderByDesc('id')
                ->first();

            $programId = $student->program_id;
            if ($student->academic_section_id) {
                $programId = DB::table('academic_sections')->where('id', $student->academic_section_id)->value('program_id') ?: $programId;
            }

            $departmentProgramId = null;
            if ($student->hte_department_id && $programId) {
                $departmentProgramId = DB::table('hte_department_programs')
                    ->where('hte_department_id', $student->hte_department_id)
                    ->where('program_id', $programId)
                    ->where('is_active', true)
                    ->value('id');
            }

            $internshipId = DB::table('student_internships')->insertGetId([
                'student_id' => $student->id,
                'ojt_enrollment_id' => $enrollment?->id,
                'academic_section_id' => $student->academic_section_id,
                'hte_id' => $student->hte_id,
                'hte_department_id' => $student->hte_department_id,
                'hte_department_program_id' => $departmentProgramId,
                'supervisor_record_id' => $student->supervisor_record_id,
                'status' => $student->internship_status ?: 'pending',
                'consent_status' => $student->consent_status ?: 'pending',
                'deployment_status' => $student->schedule_status ?: 'pending',
                'start_date' => $student->ojt_start_date,
                'end_date' => $student->ojt_end_date,
                'required_hours' => $student->required_ojt_hours ?: 486,
                'allow_past_attendance' => (bool) $student->allow_past_attendance,
                'is_current' => true,
                'created_at' => $student->created_at ?? now(),
                'updated_at' => $student->updated_at ?? now(),
            ]);

            $workDays = json_decode((string) $student->work_days, true);
            if (! is_array($workDays) || ! $student->official_am_start || ! $student->official_am_end || ! $student->official_pm_start || ! $student->official_pm_end) {
                return;
            }

            foreach (self::WEEKDAYS as $day) {
                DB::table('student_schedule_overrides')->insert([
                    'student_internship_id' => $internshipId,
                    'day_of_week' => $day,
                    'is_working' => in_array($day, $workDays, true),
                    'am_start' => in_array($day, $workDays, true) ? $student->official_am_start : null,
                    'am_end' => in_array($day, $workDays, true) ? $student->official_am_end : null,
                    'pm_start' => in_array($day, $workDays, true) ? $student->official_pm_start : null,
                    'pm_end' => in_array($day, $workDays, true) ? $student->official_pm_end : null,
                    'reason' => 'Migrated from the student schedule.',
                    'source' => 'legacy_sync',
                    'created_at' => $student->updated_at ?? now(),
                    'updated_at' => $student->updated_at ?? now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_schedule_overrides');
        Schema::dropIfExists('student_internships');
        Schema::dropIfExists('student_guardians');
    }
};
