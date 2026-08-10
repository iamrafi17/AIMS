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
        'moas',
        'attendance',
        'program_requirements',
        'internship_requirements',
        'travel_logs',
        'travel_checkpoints',
        'travel_participants',
        'travel_companions',
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
            'attendance' => ['student_id'],
            'internship_requirements' => ['student_id', 'program_requirement_id'],
            'moas' => ['hte_id'],
            'travel_checkpoints' => ['travel_log_id', 'captured_by'],
            'travel_participants' => ['travel_log_id', 'student_id'],
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
        ]));
    }

    public function test_critical_prd_uniqueness_rules_are_enforced(): void
    {
        $this->assertUniqueIndex('students', ['user_id']);
        $this->assertUniqueIndex('ojt_enrollments', ['school_id']);
        $this->assertUniqueIndex('attendance', ['student_id', 'date']);
        $this->assertUniqueIndex('travel_logs', ['session_code']);
        $this->assertUniqueIndex('travel_checkpoints', ['travel_log_id', 'sequence']);
        $this->assertUniqueIndex('travel_participants', ['travel_log_id', 'student_id']);
        $this->assertUniqueIndex('evaluations', ['student_id', 'evaluation_type']);
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
