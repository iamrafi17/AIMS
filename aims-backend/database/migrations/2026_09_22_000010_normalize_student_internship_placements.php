<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_COLUMNS = [
        'hte_program_id',
        'hte_id',
        'hte_department_id',
        'hte_department_program_id',
        'supervisor_record_id',
    ];

    public function up(): void
    {
        Schema::create('student_internship_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_internship_id')->constrained('student_internships')->cascadeOnDelete();
            $table->foreignId('hte_program_id')->nullable()->constrained('hte_programs')->restrictOnDelete();
            $table->foreignId('hte_department_program_id')->nullable()->constrained('hte_department_programs')->restrictOnDelete();
            $table->foreignId('supervisor_record_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->unsignedBigInteger('current_internship_key')->nullable()
                ->storedAs('CASE WHEN is_current = 1 THEN student_internship_id ELSE NULL END');
            $table->timestamps();

            $table->unique('current_internship_key', 'student_internship_placement_current_unique');
            $table->index(['hte_program_id', 'is_current'], 'internship_placement_hte_program_index');
            $table->index(['hte_department_program_id', 'is_current'], 'internship_placement_department_index');
            $table->index(['supervisor_record_id', 'is_current'], 'internship_placement_supervisor_index');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
                ALTER TABLE student_internship_placements
                ADD CONSTRAINT internship_placement_target_check CHECK (
                    (
                        (hte_program_id IS NOT NULL AND hte_department_program_id IS NULL)
                        OR
                        (hte_program_id IS NULL AND hte_department_program_id IS NOT NULL)
                    )
                    AND (supervisor_record_id IS NULL OR hte_department_program_id IS NOT NULL)
                )
            SQL);
        }

        $this->backfillPlacements();

        Schema::table('student_internships', function (Blueprint $table) {
            foreach (self::LEGACY_COLUMNS as $column) {
                $table->dropForeign([$column]);
            }
        });

        Schema::table('student_internships', function (Blueprint $table) {
            $table->dropColumn(self::LEGACY_COLUMNS);
        });
    }

    private function backfillPlacements(): void
    {
        DB::table('student_internships')
            ->orderBy('id')
            ->get()
            ->each(function (object $internship): void {
                $programId = $internship->academic_section_id
                    ? DB::table('academic_sections')->where('id', $internship->academic_section_id)->value('program_id')
                    : null;

                $departmentProgram = $internship->hte_department_program_id
                    ? DB::table('hte_department_programs')->where('id', $internship->hte_department_program_id)->first()
                    : null;

                if (! $departmentProgram && $internship->hte_department_id && $programId) {
                    $departmentProgram = DB::table('hte_department_programs')
                        ->where('hte_department_id', $internship->hte_department_id)
                        ->where('program_id', $programId)
                        ->first();
                }

                if ($departmentProgram) {
                    $department = DB::table('hte_departments')->where('id', $departmentProgram->hte_department_id)->first();
                    $isConsistent = $department
                        && (! $programId || (int) $departmentProgram->program_id === (int) $programId)
                        && (! $internship->hte_id || (int) $department->hte_id === (int) $internship->hte_id)
                        && (! $internship->hte_department_id || (int) $department->id === (int) $internship->hte_department_id);

                    if (! $isConsistent) {
                        throw new RuntimeException("Placement normalization failed for internship {$internship->id}: inconsistent department/program/HTE links.");
                    }
                }

                $hteProgram = null;
                if (! $departmentProgram && $internship->hte_program_id) {
                    $hteProgram = DB::table('hte_programs')->where('id', $internship->hte_program_id)->first();
                }
                if (! $departmentProgram && ! $hteProgram && $internship->hte_id && $programId) {
                    $hteProgram = DB::table('hte_programs')
                        ->where('hte_id', $internship->hte_id)
                        ->where('program_id', $programId)
                        ->first();
                }

                if ($hteProgram && (
                    ($programId && (int) $hteProgram->program_id !== (int) $programId)
                    || ($internship->hte_id && (int) $hteProgram->hte_id !== (int) $internship->hte_id)
                )) {
                    throw new RuntimeException("Placement normalization failed for internship {$internship->id}: inconsistent HTE/program link.");
                }

                if (($internship->hte_id || $internship->hte_department_id || $internship->hte_department_program_id) && ! $departmentProgram && ! $hteProgram) {
                    throw new RuntimeException("Placement normalization failed for internship {$internship->id}: no valid program-scoped HTE destination.");
                }

                if ($internship->supervisor_record_id && ! $departmentProgram) {
                    throw new RuntimeException("Placement normalization failed for internship {$internship->id}: a supervisor requires a department placement.");
                }

                if (! $departmentProgram && ! $hteProgram) {
                    return;
                }

                if ($internship->supervisor_record_id) {
                    $supervisorDepartment = DB::table('supervisors')
                        ->where('id', $internship->supervisor_record_id)
                        ->value('hte_department_id');
                    if ((int) $supervisorDepartment !== (int) $departmentProgram->hte_department_id) {
                        throw new RuntimeException("Placement normalization failed for internship {$internship->id}: supervisor is outside the selected department.");
                    }
                }

                $assignedBy = $internship->ojt_enrollment_id
                    ? DB::table('ojt_enrollments')->where('id', $internship->ojt_enrollment_id)->value('added_by')
                    : null;
                $assignedAt = $internship->updated_at ?: $internship->created_at ?: now();

                DB::table('student_internship_placements')->insert([
                    'student_internship_id' => $internship->id,
                    'hte_program_id' => $departmentProgram ? null : $hteProgram?->id,
                    'hte_department_program_id' => $departmentProgram?->id,
                    'supervisor_record_id' => $internship->supervisor_record_id,
                    'assigned_by' => $assignedBy,
                    'assigned_at' => $assignedAt,
                    'ended_at' => $internship->is_current ? null : $assignedAt,
                    'is_current' => (bool) $internship->is_current,
                    'created_at' => $assignedAt,
                    'updated_at' => $assignedAt,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('student_internships', function (Blueprint $table) {
            $table->foreignId('hte_program_id')->nullable()->after('academic_section_id')->constrained('hte_programs')->nullOnDelete();
            $table->foreignId('hte_id')->nullable()->after('hte_program_id')->constrained('htes')->nullOnDelete();
            $table->foreignId('hte_department_id')->nullable()->after('hte_id')->constrained('hte_departments')->nullOnDelete();
            $table->foreignId('hte_department_program_id')->nullable()->after('hte_department_id')->constrained('hte_department_programs')->nullOnDelete();
            $table->foreignId('supervisor_record_id')->nullable()->after('hte_department_program_id')->constrained('supervisors')->nullOnDelete();
        });

        DB::table('student_internship_placements')
            ->where('is_current', true)
            ->orderBy('id')
            ->get()
            ->each(function (object $placement): void {
                $hteId = null;
                $departmentId = null;

                if ($placement->hte_department_program_id) {
                    $link = DB::table('hte_department_programs')->where('id', $placement->hte_department_program_id)->first();
                    $departmentId = $link?->hte_department_id;
                    $hteId = $departmentId
                        ? DB::table('hte_departments')->where('id', $departmentId)->value('hte_id')
                        : null;
                } elseif ($placement->hte_program_id) {
                    $hteId = DB::table('hte_programs')->where('id', $placement->hte_program_id)->value('hte_id');
                }

                DB::table('student_internships')->where('id', $placement->student_internship_id)->update([
                    'hte_program_id' => $placement->hte_program_id,
                    'hte_id' => $hteId,
                    'hte_department_id' => $departmentId,
                    'hte_department_program_id' => $placement->hte_department_program_id,
                    'supervisor_record_id' => $placement->supervisor_record_id,
                ]);
            });

        Schema::dropIfExists('student_internship_placements');
    }
};
