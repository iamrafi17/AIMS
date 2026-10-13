<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep an index for the student foreign key before removing its old unique index.
        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->index('student_id', 'internship_requirements_student_lookup_index');
            $table->unique(['student_internship_id', 'requirement_name'], 'internship_requirements_internship_name_unique');
        });

        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->dropUnique('internship_requirements_student_requirement_unique');
            $table->dropUnique('internship_requirements_student_definition_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('internship_requirements')->select('student_id', 'requirement_name')
            ->groupBy('student_id', 'requirement_name')->havingRaw('COUNT(*) > 1')->exists()
            || DB::table('internship_requirements')->whereNotNull('program_requirement_id')
                ->select('student_id', 'program_requirement_id')->groupBy('student_id', 'program_requirement_id')
                ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore student-wide requirement uniqueness while multiple internship checklists exist.');
        }

        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->unique(['student_id', 'requirement_name'], 'internship_requirements_student_requirement_unique');
            $table->unique(['student_id', 'program_requirement_id'], 'internship_requirements_student_definition_unique');
        });
        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->dropUnique('internship_requirements_internship_name_unique');
            $table->dropIndex('internship_requirements_student_lookup_index');
        });
    }
};
