<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoreDatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const CORE_TABLES = [
        'users',
        'students',
        'ojt_enrollments',
        'colleges',
        'programs',
        'academic_sections',
        'htes',
        'hte_batch_schedules',
        'moas',
        'attendance',
        'program_requirements',
        'internship_requirements',
        'travel_logs',
        'travel_checkpoints',
        'travel_participants',
        'travel_reports',
        'travel_report_validations',
        'internship_tasks',
        'evaluations',
        'approval_records',
        'announcements',
        'announcement_reads',
        'system_notifications',
        'activity_logs',
        'holidays',
        'security_questions',
        'user_security_questions',
        'system_settings',
    ];

    public function test_all_prd_core_entities_exist(): void
    {
        foreach (self::CORE_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing PRD core table: {$table}");
        }
    }

    public function test_prd_relationship_columns_exist(): void
    {
        $relationships = [
            'students' => ['user_id', 'program_id', 'academic_section_id', 'hte_id', 'supervisor_id'],
            'ojt_enrollments' => ['student_record_id', 'college_id', 'program_id', 'academic_section_id'],
            'programs' => ['college_id'],
            'hte_batch_schedules' => ['hte_id', 'academic_section_id'],
            'attendance' => ['student_id'],
            'internship_requirements' => ['student_id', 'program_requirement_id'],
            'moas' => ['hte_id'],
            'travel_checkpoints' => ['travel_log_id', 'captured_by'],
            'travel_participants' => ['travel_log_id', 'student_id'],
            'travel_reports' => ['travel_log_id', 'coordinator_id'],
            'travel_report_validations' => ['travel_report_id', 'reviewer_id'],
            'internship_tasks' => ['student_id', 'supervisor_id'],
            'evaluations' => ['student_id', 'supervisor_id'],
        ];

        foreach ($relationships as $table => $columns) {
            $this->assertTrue(
                Schema::hasColumns($table, $columns),
                "Missing PRD relationship column on {$table}",
            );
        }
    }

    public function test_prd_workflow_fields_exist(): void
    {
        $this->assertFalse(Schema::hasColumn('travel_logs', 'student_id'));

        $this->assertTrue(Schema::hasColumns('approval_records', [
            'subject_type',
            'subject_id',
            'submitted_by',
            'submitted_at',
            'status',
            'remarks',
            'decided_by',
            'decided_at',
        ]));

        $this->assertTrue(Schema::hasColumns('travel_checkpoints', [
            'sequence',
            'point_type',
            'is_final_destination',
            'latitude',
            'longitude',
            'readable_location',
            'captured_at',
            'gps_accuracy',
        ]));

        $this->assertTrue(Schema::hasColumns('travel_logs', [
            'created_by', 'coordinator_id', 'hte_id', 'start_time', 'departed_at', 'arrived_at', 'end_time', 'status',
        ]));

        $this->assertTrue(Schema::hasColumns('travel_reports', [
            'travel_log_id', 'report_number', 'remarks', 'status', 'version', 'generated_at', 'submitted_at',
        ]));

        $this->assertTrue(Schema::hasColumn('hte_batch_schedules', 'is_working'));
    }

    public function test_critical_prd_uniqueness_rules_are_enforced(): void
    {
        $this->assertUniqueIndex('students', ['user_id']);
        $this->assertUniqueIndex('ojt_enrollments', ['school_id']);
        $this->assertUniqueIndex('attendance', ['student_id', 'date']);
        $this->assertUniqueIndex('hte_batch_schedules', ['hte_id', 'academic_section_id', 'day_of_week']);
        $this->assertUniqueIndex('travel_logs', ['session_code']);
        $this->assertUniqueIndex('travel_checkpoints', ['travel_log_id', 'sequence']);
        $this->assertUniqueIndex('travel_participants', ['travel_log_id', 'student_id']);
        $this->assertUniqueIndex('evaluations', ['student_id', 'evaluation_type']);
    }

    public function test_contact_number_columns_use_consistent_names(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'contact_number'));
        $this->assertTrue(Schema::hasColumns('students', ['contact_number', 'parent_contact_number']));
        $this->assertTrue(Schema::hasColumn('htes', 'contact_number'));

        $this->assertFalse(Schema::hasColumn('users', 'phone'));
        $this->assertFalse(Schema::hasColumn('students', 'phone'));
        $this->assertFalse(Schema::hasColumn('students', 'parent_phone'));
        $this->assertFalse(Schema::hasColumn('htes', 'contact_phone'));
    }

    private function assertUniqueIndex(string $table, array $columns): void
    {
        $hasIndex = collect(Schema::getIndexes($table))->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === $columns,
        );

        $this->assertTrue($hasIndex, sprintf(
            'Missing unique index on %s (%s)',
            $table,
            implode(', ', $columns),
        ));
    }
}
