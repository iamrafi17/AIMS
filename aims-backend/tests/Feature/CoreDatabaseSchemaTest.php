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
        'student_guardians',
        'student_internships',
        'student_internship_placements',
        'student_schedule_overrides',
        'ojt_enrollments',
        'colleges',
        'programs',
        'academic_sections',
        'htes',
        'hte_programs',
        'hte_departments',
        'hte_department_programs',
        'supervisors',
        'hte_schedules',
        'hte_batch_schedules',
        'moas',
        'attendance',
        'attendance_schedule_snapshots',
        'attendance_events',
        'attendance_journals',
        'attendance_reviews',
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
            'students' => ['user_id'],
            'student_guardians' => ['student_id'],
            'student_internships' => [
                'student_id', 'ojt_enrollment_id', 'academic_section_id',
            ],
            'student_internship_placements' => [
                'student_internship_id', 'hte_program_id', 'hte_department_program_id',
                'supervisor_record_id', 'assigned_by',
            ],
            'student_schedule_overrides' => ['student_internship_id', 'approved_by'],
            'ojt_enrollments' => ['student_record_id', 'academic_section_id'],
            'programs' => ['college_id'],
            'hte_programs' => ['hte_id', 'program_id', 'linked_by'],
            'hte_departments' => ['hte_id'],
            'hte_department_programs' => ['hte_department_id', 'program_id', 'linked_by'],
            'supervisors' => ['hte_department_id', 'user_id'],
            'hte_schedules' => ['hte_id'],
            'hte_batch_schedules' => ['hte_id', 'academic_section_id'],
            'attendance' => ['student_internship_id', 'holiday_id'],
            'attendance_schedule_snapshots' => ['attendance_id', 'hte_schedule_id', 'hte_batch_schedule_id', 'student_schedule_override_id'],
            'attendance_events' => ['attendance_id', 'recorded_by'],
            'attendance_journals' => ['attendance_id', 'reviewed_by'],
            'attendance_reviews' => ['attendance_id', 'reviewed_by'],
            'internship_requirements' => ['student_id', 'student_internship_id', 'program_requirement_id'],
            'moas' => ['hte_id'],
            'travel_checkpoints' => ['travel_log_id', 'captured_by'],
            'travel_participants' => ['travel_log_id', 'student_id'],
            'travel_reports' => ['travel_log_id', 'coordinator_id'],
            'travel_report_validations' => ['travel_report_id', 'reviewer_id'],
            'internship_tasks' => ['student_id', 'student_internship_id', 'supervisor_id'],
            'evaluations' => ['student_id', 'student_internship_id', 'supervisor_id'],
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

        $this->assertFalse(Schema::hasColumn('htes', 'college_id'));
        $this->assertFalse(Schema::hasColumn('htes', 'program_id'));
        $this->assertFalse(Schema::hasColumn('hte_programs', 'college_id'));

        foreach (['default_am_start', 'default_am_end', 'default_pm_start', 'default_pm_end', 'work_days'] as $column) {
            $this->assertFalse(Schema::hasColumn('htes', $column));
        }

        $this->assertFalse(Schema::hasColumn('student_internships', 'college_id'));
        $this->assertFalse(Schema::hasColumn('student_internships', 'program_id'));
        foreach (['hte_program_id', 'hte_id', 'hte_department_id', 'hte_department_program_id', 'supervisor_record_id'] as $normalizedColumn) {
            $this->assertFalse(Schema::hasColumn('student_internships', $normalizedColumn));
        }

        foreach (['section', 'college_id', 'program_id', 'academic_period_id'] as $normalizedColumn) {
            $this->assertFalse(Schema::hasColumn('ojt_enrollments', $normalizedColumn));
        }

        $this->assertFalse(Schema::hasColumn('academic_sections', 'academic_year'));
        $sectionPeriod = collect(Schema::getColumns('academic_sections'))
            ->firstWhere('name', 'academic_period_id');
        $this->assertFalse($sectionPeriod['nullable']);
        $enrollmentSection = collect(Schema::getColumns('ojt_enrollments'))
            ->firstWhere('name', 'academic_section_id');
        $this->assertFalse($enrollmentSection['nullable']);
        $this->assertIndex('ojt_enrollments', ['academic_section_id', 'registered_at']);

        $this->assertTrue(Schema::hasColumns('report_templates', ['period_scope', 'academic_period_id', 'validation_result']));
        foreach (['academic_year', 'semester', 'detected_placeholders'] as $normalizedColumn) {
            $this->assertFalse(Schema::hasColumn('report_templates', $normalizedColumn));
        }
        $this->assertIndex('report_templates', ['program_id', 'report_type', 'period_scope', 'academic_period_id']);

        foreach ([
            'college_id', 'program_id', 'academic_section_id', 'academic_period_id',
            'year_level', 'section', 'parent_name', 'parent_relationship', 'parent_address',
            'parent_contact_number', 'hte_id', 'hte_department_id', 'supervisor_record_id',
            'supervisor_id', 'internship_semester', 'internship_year', 'internship_status',
            'registration_status', 'registration_feedback', 'registration_reviewed_by',
            'registration_reviewed_at', 'consent_status', 'schedule_status', 'ojt_start_date',
            'ojt_end_date', 'required_ojt_hours', 'allow_past_attendance', 'official_am_start',
            'official_am_end', 'official_pm_start', 'official_pm_end', 'work_days',
        ] as $normalizedColumn) {
            $this->assertFalse(Schema::hasColumn('students', $normalizedColumn));
        }

        foreach ([
            'student_id', 'time_in', 'time_out', 'am_time_in', 'am_late_minutes', 'am_time_out',
            'pm_time_in', 'pm_late_minutes', 'pm_time_out', 'ot_start', 'ot_end', 'overtime_hours',
            'am_activity', 'pm_activity', 'journal_status', 'journal_feedback', 'journal_reviewed_by',
            'journal_reviewed_at', 'am_time_in_location', 'am_time_out_location',
            'pm_time_in_location', 'pm_time_out_location', 'latitude_in', 'longitude_in',
            'latitude_out', 'longitude_out', 'is_verified', 'supervisor_review_status',
            'supervisor_feedback', 'supervisor_reviewed_by', 'supervisor_reviewed_at',
            'verified_by', 'verified_at',
        ] as $normalizedColumn) {
            $this->assertFalse(Schema::hasColumn('attendance', $normalizedColumn));
        }

        $this->assertTrue(Schema::hasColumns('attendance', [
            'id', 'student_internship_id', 'date', 'work_mode', 'session_type', 'status',
            'holiday_id', 'created_at', 'updated_at',
        ]));
        $attendanceInternship = collect(Schema::getColumns('attendance'))
            ->firstWhere('name', 'student_internship_id');
        $this->assertFalse($attendanceInternship['nullable']);
    }

    public function test_critical_prd_uniqueness_rules_are_enforced(): void
    {
        $this->assertUniqueIndex('students', ['user_id']);
        $this->assertUniqueIndex('student_guardians', ['student_id']);
        $this->assertUniqueIndex('student_internships', ['student_id', 'academic_section_id']);
        $this->assertUniqueIndex('student_internship_placements', ['current_internship_key']);
        $this->assertUniqueIndex('student_schedule_overrides', ['student_internship_id', 'day_of_week']);
        $this->assertUniqueIndex('ojt_enrollments', ['school_id']);
        $this->assertUniqueIndex('attendance', ['student_internship_id', 'date']);
        $this->assertUniqueIndex('attendance_schedule_snapshots', ['attendance_id', 'period']);
        $this->assertUniqueIndex('attendance_events', ['attendance_id', 'event_type']);
        $this->assertUniqueIndex('attendance_journals', ['attendance_id', 'period']);
        $this->assertUniqueIndex('attendance_reviews', ['attendance_id', 'review_type']);
        $this->assertUniqueIndex('hte_schedules', ['hte_id', 'day_of_week']);
        $this->assertUniqueIndex('hte_batch_schedules', ['hte_id', 'academic_section_id', 'day_of_week']);
        $this->assertUniqueIndex('hte_programs', ['hte_id', 'program_id']);
        $this->assertUniqueIndex('hte_department_programs', ['hte_department_id', 'program_id']);
        $this->assertUniqueIndex('travel_logs', ['session_code']);
        $this->assertUniqueIndex('travel_checkpoints', ['travel_log_id', 'sequence']);
        $this->assertUniqueIndex('travel_participants', ['travel_log_id', 'student_id']);
        $this->assertUniqueIndex('evaluations', ['student_id', 'evaluation_type']);
        $this->assertUniqueIndex('evaluations', ['student_internship_id', 'evaluation_type']);
    }

    public function test_contact_number_columns_use_consistent_names(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'contact_number'));
        $this->assertTrue(Schema::hasColumn('students', 'contact_number'));
        $this->assertTrue(Schema::hasColumn('htes', 'contact_number'));

        $this->assertFalse(Schema::hasColumn('users', 'phone'));
        $this->assertFalse(Schema::hasColumn('students', 'phone'));
        $this->assertFalse(Schema::hasColumn('students', 'parent_phone'));
        $this->assertFalse(Schema::hasColumn('students', 'parent_contact_number'));
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

    private function assertIndex(string $table, array $columns): void
    {
        $hasIndex = collect(Schema::getIndexes($table))->contains(
            fn (array $index) => $index['columns'] === $columns,
        );

        $this->assertTrue($hasIndex, sprintf(
            'Missing index on %s (%s)',
            $table,
            implode(', ', $columns),
        ));
    }
}
