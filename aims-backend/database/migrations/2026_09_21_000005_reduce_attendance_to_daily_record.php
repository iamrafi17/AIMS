<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_COLUMNS = [
        'student_id', 'time_in', 'time_out',
        'am_time_in', 'am_late_minutes', 'am_time_out',
        'pm_time_in', 'pm_late_minutes', 'pm_time_out',
        'ot_start', 'ot_end', 'overtime_hours',
        'am_activity', 'pm_activity',
        'journal_status', 'journal_feedback', 'journal_reviewed_by', 'journal_reviewed_at',
        'am_time_in_location', 'am_time_out_location', 'pm_time_in_location', 'pm_time_out_location',
        'latitude_in', 'longitude_in', 'latitude_out', 'longitude_out',
        'is_verified', 'supervisor_review_status', 'supervisor_feedback',
        'supervisor_reviewed_by', 'supervisor_reviewed_at', 'verified_by', 'verified_at',
    ];

    public function up(): void
    {
        $this->assertBackfillComplete();
        $isSqlite = DB::getDriverName() === 'sqlite';

        Schema::table('attendance', function (Blueprint $table) use ($isSqlite) {
            $isSqlite
                ? $table->dropForeign(['student_internship_id', 'student_id'])
                : $table->dropForeign('attendance_internship_student_foreign');
            $table->foreign('student_internship_id')
                ->references('id')->on('student_internships')->restrictOnDelete();
            $table->dropForeign(['student_id']);
            $table->dropForeign(['journal_reviewed_by']);
            $table->dropForeign(['supervisor_reviewed_by']);
            $table->dropForeign(['verified_by']);
            $table->dropUnique('attendance_student_id_date_unique');
            $table->dropIndex('attendance_status_verified_date_index');
            $table->dropIndex('attendance_journal_date_index');
        });

        Schema::table('attendance', fn (Blueprint $table) => $table->dropColumn(self::LEGACY_COLUMNS));
    }

    private function assertBackfillComplete(): void
    {
        if (DB::table('attendance')->whereNull('student_internship_id')->exists()) {
            throw new RuntimeException('Attendance normalization failed: an attendance row has no internship link.');
        }

        $missingSnapshots = DB::table('attendance as attendance')
            ->leftJoin('attendance_schedule_snapshots as snapshots', 'snapshots.attendance_id', '=', 'attendance.id')
            ->groupBy('attendance.id')
            ->havingRaw('COUNT(snapshots.id) <> 2')
            ->exists();

        if ($missingSnapshots) {
            throw new RuntimeException('Attendance normalization failed: schedule snapshots are incomplete.');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restore the attendance normalization migration from a database backup.');
    }
};
