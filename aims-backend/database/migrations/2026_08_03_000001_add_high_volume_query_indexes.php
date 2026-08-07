<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'is_active', 'name'], 'users_role_active_name_index');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index(['program_id', 'internship_status', 'last_name'], 'students_program_status_name_index');
            $table->index(['program_id', 'registration_status'], 'students_program_registration_index');
            $table->index(['program_id', 'hte_id'], 'students_program_hte_index');
            $table->index(['supervisor_id', 'internship_status'], 'students_supervisor_status_index');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->index(['date', 'status'], 'attendance_date_status_index');
            $table->index(['status', 'is_verified', 'date'], 'attendance_status_verified_date_index');
            $table->index(['journal_status', 'date'], 'attendance_journal_date_index');
        });

        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->index(['student_id', 'status'], 'requirements_student_status_index');
        });

        Schema::table('travel_logs', function (Blueprint $table) {
            $table->index(['status', 'start_time'], 'travel_logs_status_start_index');
            $table->index(['student_id', 'start_time'], 'travel_logs_student_start_index');
        });

        Schema::table('moas', function (Blueprint $table) {
            $table->index(['program_id', 'status', 'expiration_date'], 'moas_program_status_expiry_index');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['is_published', 'target_audience', 'published_at'], 'announcements_audience_published_index');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', fn (Blueprint $table) => $table->dropIndex('announcements_audience_published_index'));
        Schema::table('moas', fn (Blueprint $table) => $table->dropIndex('moas_program_status_expiry_index'));
        Schema::table('travel_logs', function (Blueprint $table) {
            $table->dropIndex('travel_logs_status_start_index');
            $table->dropIndex('travel_logs_student_start_index');
        });
        Schema::table('internship_requirements', fn (Blueprint $table) => $table->dropIndex('requirements_student_status_index'));
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('attendance_date_status_index');
            $table->dropIndex('attendance_status_verified_date_index');
            $table->dropIndex('attendance_journal_date_index');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_program_status_name_index');
            $table->dropIndex('students_program_registration_index');
            $table->dropIndex('students_program_hte_index');
            $table->dropIndex('students_supervisor_status_index');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_role_active_name_index'));
    }
};
