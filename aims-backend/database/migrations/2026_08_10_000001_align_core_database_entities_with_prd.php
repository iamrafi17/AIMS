<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('academic_section_id')
                ->nullable()
                ->after('program_id')
                ->constrained('academic_sections')
                ->nullOnDelete();

            $table->unique('user_id', 'students_user_unique');
        });

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->foreignId('academic_section_id')
                ->nullable()
                ->after('program_id')
                ->constrained('academic_sections')
                ->nullOnDelete();
        });

        Schema::table('travel_logs', function (Blueprint $table) {
            $table->unique('session_code', 'travel_logs_session_code_unique');
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->text('readable_location')->nullable()->after('longitude');
            $table->foreignId('captured_by')
                ->nullable()
                ->after('captured_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->unique(
                ['travel_log_id', 'sequence'],
                'travel_checkpoints_session_sequence_unique',
            );
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'evaluation_type'],
                'evaluations_student_type_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropUnique('evaluations_student_type_unique');
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->dropUnique('travel_checkpoints_session_sequence_unique');
            $table->dropConstrainedForeignId('captured_by');
            $table->dropColumn('readable_location');
        });

        Schema::table('travel_logs', function (Blueprint $table) {
            $table->dropUnique('travel_logs_session_code_unique');
        });

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_section_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_user_unique');
            $table->dropConstrainedForeignId('academic_section_id');
        });
    }
};
