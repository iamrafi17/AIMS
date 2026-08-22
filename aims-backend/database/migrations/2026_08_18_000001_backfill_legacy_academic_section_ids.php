<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_sections')
            || ! Schema::hasColumn('students', 'academic_section_id')
            || ! Schema::hasColumn('ojt_enrollments', 'academic_section_id')) {
            return;
        }

        $sections = DB::table('academic_sections')
            ->get(['id', 'program_id', 'name', 'is_active'])
            ->keyBy('id');

        $this->copyEnrollmentSectionsToStudents($sections);
        $this->copyStudentSectionsToEnrollments($sections);
        $this->backfillUniqueExactNames('students', $sections);
        $this->backfillUniqueExactNames('ojt_enrollments', $sections);
        $this->canonicalizeSectionNames('students', $sections);
        $this->canonicalizeSectionNames('ojt_enrollments', $sections);
    }

    public function down(): void
    {
        // This data repair is intentionally irreversible. Clearing the values on
        // rollback could erase section assignments created after this migration.
    }

    private function copyEnrollmentSectionsToStudents($sections): void
    {
        DB::table('ojt_enrollments')
            ->whereNotNull('student_record_id')
            ->whereNotNull('academic_section_id')
            ->orderBy('id')
            ->chunkById(500, function ($enrollments) use ($sections): void {
                foreach ($enrollments as $enrollment) {
                    $section = $sections->get($enrollment->academic_section_id);

                    if (! $section || (int) $section->program_id !== (int) $enrollment->program_id) {
                        continue;
                    }

                    DB::table('students')
                        ->where('id', $enrollment->student_record_id)
                        ->where('program_id', $enrollment->program_id)
                        ->whereNull('academic_section_id')
                        ->update([
                            'academic_section_id' => $section->id,
                            'section' => $section->name,
                        ]);
                }
            });
    }

    private function copyStudentSectionsToEnrollments($sections): void
    {
        DB::table('students')
            ->whereNotNull('academic_section_id')
            ->orderBy('id')
            ->chunkById(500, function ($students) use ($sections): void {
                foreach ($students as $student) {
                    $section = $sections->get($student->academic_section_id);

                    if (! $section || (int) $section->program_id !== (int) $student->program_id) {
                        continue;
                    }

                    DB::table('ojt_enrollments')
                        ->where('student_record_id', $student->id)
                        ->where('program_id', $student->program_id)
                        ->whereNull('academic_section_id')
                        ->update([
                            'academic_section_id' => $section->id,
                            'section' => $section->name,
                        ]);
                }
            });
    }

    private function backfillUniqueExactNames(string $table, $sections): void
    {
        $activeByProgram = $sections
            ->filter(fn ($section) => (bool) $section->is_active)
            ->groupBy(fn ($section) => (int) $section->program_id);

        DB::table($table)
            ->whereNull('academic_section_id')
            ->whereNotNull('program_id')
            ->whereNotNull('section')
            ->orderBy('id')
            ->chunkById(500, function ($records) use ($activeByProgram, $table): void {
                foreach ($records as $record) {
                    $legacyName = $this->normalize($record->section);

                    if ($legacyName === '') {
                        continue;
                    }

                    $matches = $activeByProgram
                        ->get((int) $record->program_id, collect())
                        ->filter(fn ($section) => $this->normalize($section->name) === $legacyName)
                        ->values();

                    if ($matches->count() !== 1) {
                        continue;
                    }

                    $section = $matches->first();
                    DB::table($table)
                        ->where('id', $record->id)
                        ->whereNull('academic_section_id')
                        ->update([
                            'academic_section_id' => $section->id,
                            'section' => $section->name,
                        ]);
                }
            });
    }

    private function canonicalizeSectionNames(string $table, $sections): void
    {
        DB::table($table)
            ->whereNotNull('academic_section_id')
            ->orderBy('id')
            ->chunkById(500, function ($records) use ($sections, $table): void {
                foreach ($records as $record) {
                    $section = $sections->get($record->academic_section_id);

                    if (! $section
                        || (int) $section->program_id !== (int) $record->program_id
                        || $record->section === $section->name) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $record->id)
                        ->where('academic_section_id', $section->id)
                        ->update(['section' => $section->name]);
                }
            });
    }

    private function normalize(?string $value): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $value)));
    }
};
