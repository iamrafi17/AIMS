<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $placements = DB::table('student_internship_placements as placement')
            ->join('supervisors as supervisor', 'supervisor.id', '=', 'placement.supervisor_record_id')
            ->join('hte_department_programs as department_program', 'department_program.id', '=', 'placement.hte_department_program_id')
            ->leftJoin('supervisor_profiles as profile', 'profile.id', '=', 'supervisor.id')
            ->leftJoin('hte_supervisor_assignments as assignment', function ($join): void {
                $join->on('assignment.supervisor_profile_id', '=', 'profile.id')
                    ->on('assignment.hte_department_id', '=', 'department_program.hte_department_id');
            })
            ->whereNotNull('placement.supervisor_record_id')
            ->get(['placement.id', 'assignment.id as assignment_id']);

        foreach ($placements as $placement) {
            if ($placement->assignment_id === null) {
                throw new RuntimeException("Placement {$placement->id} has no matching supervisor assignment.");
            }
        }

        // The legacy column remains as an API compatibility field, but its foreign key
        // now identifies the supervisor's department assignment rather than a copy of it.
        Schema::table('student_internship_placements', function (Blueprint $table): void {
            $table->dropForeign(['supervisor_record_id']);
        });

        foreach ($placements as $placement) {
            DB::table('student_internship_placements')->where('id', $placement->id)
                ->update(['supervisor_record_id' => $placement->assignment_id]);
        }

        Schema::table('student_internship_placements', function (Blueprint $table): void {
            $table->foreign('supervisor_record_id', 'placement_supervisor_assignment_fk')
                ->references('id')->on('hte_supervisor_assignments')->nullOnDelete();
        });

        Schema::drop('supervisors');
    }

    public function down(): void
    {
        $assignments = DB::table('hte_supervisor_assignments as assignment')
            ->join('supervisor_profiles as profile', 'profile.id', '=', 'assignment.supervisor_profile_id')
            ->get([
                'assignment.id', 'assignment.hte_department_id', 'assignment.is_active',
                'profile.full_name', 'profile.user_id', 'assignment.created_at', 'assignment.updated_at',
            ]);

        $duplicateName = $assignments->groupBy(fn ($assignment) => $assignment->hte_department_id.'|'.mb_strtolower(
            preg_replace('/\s+/', ' ', trim($assignment->full_name)),
        ))->first(fn ($group) => $group->count() > 1);
        if ($duplicateName) {
            throw new RuntimeException('Cannot restore legacy supervisors: duplicate names exist in one department.');
        }

        Schema::create('supervisors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hte_department_id')->constrained('hte_departments')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('normalized_name');
            $table->foreignId('user_id')->nullable()->unique('supervisors_user_unique')->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['hte_department_id', 'normalized_name'], 'supervisor_assignment_name_unique');
        });

        foreach ($assignments as $assignment) {
            DB::table('supervisors')->insert([
                'id' => $assignment->id,
                'hte_department_id' => $assignment->hte_department_id,
                'full_name' => $assignment->full_name,
                'normalized_name' => mb_strtolower(preg_replace('/\s+/', ' ', trim($assignment->full_name))),
                'user_id' => $assignment->is_active ? $assignment->user_id : null,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ]);
        }

        Schema::table('student_internship_placements', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite'
                ? ['supervisor_record_id']
                : 'placement_supervisor_assignment_fk');
            $table->foreign('supervisor_record_id', 'student_internship_placements_supervisor_record_id_foreign')
                ->references('id')->on('supervisors')->nullOnDelete();
        });
    }
};
