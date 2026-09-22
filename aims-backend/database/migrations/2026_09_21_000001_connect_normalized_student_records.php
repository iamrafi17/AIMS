<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->string('registration_status', 20)->default('pending')->after('student_record_id');
            $table->text('registration_feedback')->nullable()->after('registration_status');
            $table->foreignId('registration_reviewed_by')->nullable()->after('registration_feedback')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('registration_reviewed_at')->nullable()->after('registration_reviewed_by');
        });

        Schema::table('student_internships', function (Blueprint $table) {
            $table->foreignId('hte_program_id')->nullable()->after('academic_section_id')
                ->constrained('hte_programs')->nullOnDelete();
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->foreignId('student_internship_id')->nullable()->after('student_id')
                ->constrained('student_internships')->restrictOnDelete();
        });

        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->foreignId('student_internship_id')->nullable()->after('student_id')
                ->constrained('student_internships')->restrictOnDelete();
        });

        Schema::table('internship_tasks', function (Blueprint $table) {
            $table->foreignId('student_internship_id')->nullable()->after('student_id')
                ->constrained('student_internships')->restrictOnDelete();
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreignId('student_internship_id')->nullable()->after('student_id')
                ->constrained('student_internships')->restrictOnDelete();
        });

        $this->backfillRegistrationReviews();
        $this->backfillHteProgramLinks();
        $this->backfillInternshipTransactions();

        Schema::table('attendance', function (Blueprint $table) {
            $table->unique(['student_internship_id', 'date'], 'attendance_internship_date_unique');
        });

        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->unique(
                ['student_internship_id', 'program_requirement_id'],
                'internship_requirement_definition_unique',
            );
        });

        Schema::table('internship_tasks', function (Blueprint $table) {
            $table->index(['student_internship_id', 'status'], 'internship_tasks_status_index');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->unique(
                ['student_internship_id', 'evaluation_type'],
                'evaluations_internship_type_unique',
            );
        });
    }

    private function backfillRegistrationReviews(): void
    {
        DB::table('ojt_enrollments as enrollments')
            ->join('students', 'students.id', '=', 'enrollments.student_record_id')
            ->select([
                'enrollments.id',
                'students.registration_status',
                'students.registration_feedback',
                'students.registration_reviewed_by',
                'students.registration_reviewed_at',
            ])
            ->orderBy('enrollments.id')
            ->each(function (object $row): void {
                DB::table('ojt_enrollments')->where('id', $row->id)->update([
                    'registration_status' => $row->registration_status ?: 'pending',
                    'registration_feedback' => $row->registration_feedback,
                    'registration_reviewed_by' => $row->registration_reviewed_by,
                    'registration_reviewed_at' => $row->registration_reviewed_at,
                ]);
            });
    }

    private function backfillHteProgramLinks(): void
    {
        DB::table('student_internships as internships')
            ->leftJoin('academic_sections as sections', 'sections.id', '=', 'internships.academic_section_id')
            ->leftJoin('students', 'students.id', '=', 'internships.student_id')
            ->whereNotNull('internships.hte_id')
            ->select([
                'internships.id',
                'internships.hte_id',
                'sections.program_id as section_program_id',
                'students.program_id as legacy_program_id',
            ])
            ->orderBy('internships.id')
            ->each(function (object $row): void {
                $programId = $row->section_program_id ?: $row->legacy_program_id;
                if (! $programId) {
                    return;
                }

                $hteProgramId = DB::table('hte_programs')
                    ->where('hte_id', $row->hte_id)
                    ->where('program_id', $programId)
                    ->value('id');

                if ($hteProgramId) {
                    DB::table('student_internships')->where('id', $row->id)->update([
                        'hte_program_id' => $hteProgramId,
                    ]);
                }
            });
    }

    private function backfillInternshipTransactions(): void
    {
        $currentInternships = DB::table('student_internships')
            ->where('is_current', true)
            ->orderByDesc('id')
            ->get(['id', 'student_id'])
            ->unique('student_id')
            ->keyBy('student_id');

        foreach (['attendance', 'internship_requirements', 'internship_tasks', 'evaluations'] as $table) {
            DB::table($table)
                ->whereNull('student_internship_id')
                ->orderBy('id')
                ->get(['id', 'student_id'])
                ->each(function (object $row) use ($currentInternships, $table): void {
                    $internship = $currentInternships->get($row->student_id);
                    if ($internship) {
                        DB::table($table)->where('id', $row->id)->update([
                            'student_internship_id' => $internship->id,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropUnique('evaluations_internship_type_unique');
            $table->dropConstrainedForeignId('student_internship_id');
        });

        Schema::table('internship_tasks', function (Blueprint $table) {
            $table->dropIndex('internship_tasks_status_index');
            $table->dropConstrainedForeignId('student_internship_id');
        });

        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->dropUnique('internship_requirement_definition_unique');
            $table->dropConstrainedForeignId('student_internship_id');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropUnique('attendance_internship_date_unique');
            $table->dropConstrainedForeignId('student_internship_id');
        });

        Schema::table('student_internships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hte_program_id');
        });

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registration_reviewed_by');
            $table->dropColumn([
                'registration_status',
                'registration_feedback',
                'registration_reviewed_at',
            ]);
        });
    }
};
