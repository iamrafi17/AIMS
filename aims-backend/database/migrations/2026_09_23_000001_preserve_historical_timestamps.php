<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * These columns record when a business event happened. They must not be
     * changed implicitly when another attribute on the same row is updated.
     */
    private const HISTORICAL_TIMESTAMPS = [
        'announcement_reads' => 'read_at',
        'approval_records' => 'submitted_at',
        'attendance_events' => 'occurred_at',
        'student_internship_placements' => 'assigned_at',
        'student_live_locations' => 'captured_at',
        'travel_checkpoints' => 'captured_at',
        'travel_reports' => 'generated_at',
        'travel_report_validations' => 'reviewed_at',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::HISTORICAL_TIMESTAMPS as $table => $column) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY COLUMN `%s` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
                $table,
                $column,
            ));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::HISTORICAL_TIMESTAMPS as $table => $column) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY COLUMN `%s` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                $table,
                $column,
            ));
        }
    }
};
