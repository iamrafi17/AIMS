<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\AcademicSection;
use App\Models\HteBatchSchedule;
use App\Models\OjtEnrollment;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\StudentInternship;
use App\Models\StudentInternshipPlacement;
use App\Models\StudentScheduleOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentRecordNormalizer
{
    private const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public static function sync(Student $student): ?StudentInternship
    {
        if (! Schema::hasTable('student_internships')) {
            return null;
        }

        return DB::transaction(function () use ($student): StudentInternship {
            self::syncGuardian($student);

            $academicPeriodId = $student->academic_period_id;
            if (! $academicPeriodId && $student->academic_section_id) {
                $academicPeriodId = DB::table('academic_sections')
                    ->where('id', $student->academic_section_id)
                    ->value('academic_period_id');
            }
            if (! $academicPeriodId && $student->internship_year && $student->internship_semester) {
                $year = AcademicPeriod::normalizeYear((string) $student->internship_year);
                $semester = AcademicPeriod::normalizeSemester((string) $student->internship_semester);
                $matches = AcademicPeriod::query()
                    ->where('academic_year', $year)
                    ->where('semester', $semester)
                    ->limit(2)
                    ->get();
                $academicPeriodId = $matches->count() === 1 ? $matches->first()->id : null;
            }

            if (! $student->academic_section_id && $student->program_id && filled($student->section)) {
                $academicYear = $academicPeriodId
                    ? AcademicPeriod::find($academicPeriodId)?->academic_year
                    : ($student->internship_year ? AcademicPeriod::normalizeYear((string) $student->internship_year) : now()->format('Y').'-'.now()->addYear()->format('Y'));
                $sectionQuery = AcademicSection::query()
                    ->where('program_id', $student->program_id)
                    ->when($academicPeriodId, fn ($query) => $query->where('academic_period_id', $academicPeriodId));
                $section = (clone $sectionQuery)->where('name', $student->section)->first();

                if (! $section && (clone $sectionQuery)->count() === 1) {
                    $section = $sectionQuery->first();
                }

                $section ??= AcademicSection::create([
                    'program_id' => $student->program_id,
                    'academic_period_id' => $academicPeriodId,
                    'name' => $student->section,
                    'year_level' => max((int) ($student->year_level ?: 4), 1),
                    'academic_year' => $academicYear,
                    'is_active' => true,
                ]);

                $student->setAttribute('academic_section_id', $section->id);
                $academicPeriodId ??= $section->academic_period_id;
            }

            $enrollment = OjtEnrollment::query()
                ->where('student_record_id', $student->id)
                ->orWhere(function ($query) use ($student): void {
                    $query->whereNull('student_record_id')->where('school_id', $student->student_id);
                })
                ->latest('registered_at')
                ->latest('id')
                ->first();

            if (! $enrollment && $student->academic_section_id) {
                $enrollment = OjtEnrollment::create([
                    'school_id' => $student->student_id,
                    'first_name' => $student->first_name,
                    'middle_name' => $student->middle_name,
                    'last_name' => $student->last_name,
                    'academic_section_id' => $student->academic_section_id,
                    'source' => 'manual',
                    'student_record_id' => $student->id,
                    'registered_at' => now(),
                ]);
            }

            if ($enrollment) {
                $enrollment->update([
                    'student_record_id' => $student->id,
                    'academic_section_id' => $student->academic_section_id ?: $enrollment->academic_section_id,
                    'registered_at' => $enrollment->registered_at ?: now(),
                ]);
            }

            $programId = $student->academic_section_id
                ? DB::table('academic_sections')->where('id', $student->academic_section_id)->value('program_id')
                : $student->program_id;

            $hteProgramId = null;
            if ($student->hte_id && $programId) {
                $hteProgramId = DB::table('hte_programs')
                    ->where('hte_id', $student->hte_id)
                    ->where('program_id', $programId)
                    ->where('is_active', true)
                    ->value('id');
            }

            $departmentProgramId = null;
            if ($student->hte_department_id && $programId) {
                $departmentProgramId = DB::table('hte_department_programs')
                    ->where('hte_department_id', $student->hte_department_id)
                    ->where('program_id', $programId)
                    ->where('is_active', true)
                    ->value('id');
            }

            $internshipQuery = StudentInternship::where('student_id', $student->id);
            $internship = $student->academic_section_id
                ? (clone $internshipQuery)->where('academic_section_id', $student->academic_section_id)->first()
                : (clone $internshipQuery)->whereNull('academic_section_id')->where('is_current', true)->first();

            // A profile can be created before its academic section is assigned. Reuse that
            // provisional current record so its one-to-one enrollment link stays intact.
            if (! $internship && $student->academic_section_id) {
                $internship = (clone $internshipQuery)
                    ->whereNull('academic_section_id')
                    ->where('is_current', true)
                    ->first();
            }

            $internship ??= new StudentInternship([
                'student_id' => $student->id,
                'academic_section_id' => $student->academic_section_id,
            ]);

            if ($enrollment) {
                StudentInternship::where('ojt_enrollment_id', $enrollment->id)
                    ->when($internship->exists, fn ($query) => $query->whereKeyNot($internship->id))
                    ->update(['ojt_enrollment_id' => null]);
            }

            $internship->fill([
                'ojt_enrollment_id' => $enrollment?->id,
                'academic_section_id' => $student->academic_section_id,
                'status' => $student->internship_status ?: 'pending',
                'consent_status' => $student->consent_status ?: 'pending',
                'deployment_status' => $student->schedule_status ?: 'pending',
                'start_date' => $student->ojt_start_date,
                'end_date' => $student->ojt_end_date,
                'required_hours' => $student->required_ojt_hours ?: 486,
                'allow_past_attendance' => (bool) $student->allow_past_attendance,
                'is_current' => true,
            ]);
            $internship->save();
            self::syncPlacement(
                $student,
                $internship,
                $departmentProgramId,
                $departmentProgramId ? null : $hteProgramId,
            );

            if ($enrollment && Schema::hasColumn('ojt_enrollments', 'registration_status')) {
                $enrollment->update([
                    'registration_status' => $student->registration_status ?: 'pending',
                ]);
            }

            StudentInternship::where('student_id', $student->id)
                ->whereKeyNot($internship->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            if ($student->syncScheduleOverrides || $student->hasNormalizedContextInput([
                'official_am_start',
                'official_am_end',
                'official_pm_start',
                'official_pm_end',
                'work_days',
            ])) {
                self::syncScheduleOverrides($student, $internship);
            }

            $student->clearNormalizedContextInput();

            return $internship;
        });
    }

    public static function syncLegacySchedule(Student $student): void
    {
        $student->syncScheduleOverrides = true;
        $internship = self::sync($student);
        $student->syncScheduleOverrides = false;

        if ($internship) {
            $internship->scheduleOverrides()->where('source', 'legacy_sync')->update(['source' => 'manual']);
        }
    }

    public static function clearScheduleOverrides(Student $student): void
    {
        $internship = $student->currentInternship()->first() ?? self::sync($student);
        $internship?->scheduleOverrides()->delete();
    }

    public static function markScheduleOverridesManual(Student $student): void
    {
        $internship = $student->currentInternship()->first() ?? self::sync($student);
        $internship?->scheduleOverrides()->where('source', 'legacy_sync')->update(['source' => 'manual']);
    }

    private static function syncGuardian(Student $student): void
    {
        $hasGuardian = filled($student->parent_name)
            || filled($student->parent_relationship)
            || filled($student->parent_address)
            || filled($student->parent_contact_number);

        if (! $hasGuardian) {
            StudentGuardian::where('student_id', $student->id)->delete();

            return;
        }

        StudentGuardian::updateOrCreate(
            ['student_id' => $student->id],
            [
                'full_name' => $student->parent_name ?: 'Not provided',
                'relationship' => $student->parent_relationship,
                'address' => $student->parent_address,
                'contact_number' => $student->parent_contact_number,
            ],
        );
    }

    private static function syncPlacement(
        Student $student,
        StudentInternship $internship,
        ?int $departmentProgramId,
        ?int $hteProgramId,
    ): void {
        $hasRequestedHte = filled($student->hte_id);
        if ($hasRequestedHte && ! $departmentProgramId && ! $hteProgramId) {
            throw new \LogicException('The selected HTE is not linked to the student program.');
        }

        $supervisorId = $student->supervisor_record_id ?: null;
        if ($supervisorId && ! $departmentProgramId) {
            throw new \LogicException('A supervisor requires a department placement.');
        }

        $current = StudentInternshipPlacement::query()
            ->where('student_internship_id', $internship->id)
            ->where('is_current', true)
            ->lockForUpdate()
            ->first();

        if (! $departmentProgramId && ! $hteProgramId) {
            if ($current) {
                $current->update(['is_current' => false, 'ended_at' => now()]);
            }
            $internship->clearPlacementContext();

            return;
        }

        $unchanged = $current
            && (int) $current->hte_department_program_id === (int) $departmentProgramId
            && (int) $current->hte_program_id === (int) $hteProgramId
            && (int) $current->supervisor_record_id === (int) $supervisorId;

        if ($unchanged) {
            if ($student->placementAssignedBy && ! $current->assigned_by) {
                $current->update(['assigned_by' => $student->placementAssignedBy]);
            }
            $internship->clearPlacementContext();

            return;
        }

        if ($current) {
            $current->update(['is_current' => false, 'ended_at' => now()]);
        }

        StudentInternshipPlacement::create([
            'student_internship_id' => $internship->id,
            'hte_program_id' => $hteProgramId,
            'hte_department_program_id' => $departmentProgramId,
            'supervisor_record_id' => $supervisorId,
            'assigned_by' => $student->placementAssignedBy ?: $internship->enrollment?->added_by,
            'assigned_at' => now(),
            'is_current' => true,
        ]);
        $internship->clearPlacementContext();
    }

    private static function syncScheduleOverrides(Student $student, StudentInternship $internship): void
    {
        $workDays = is_array($student->work_days)
            ? collect($student->work_days)
                ->map(fn ($day) => strtolower(substr((string) $day, 0, 3)))
                ->unique()
                ->values()
                ->all()
            : [];
        $hasCompleteSchedule = $workDays
            && $student->official_am_start && $student->official_am_end
            && $student->official_pm_start && $student->official_pm_end;

        if (! $hasCompleteSchedule) {
            $internship->scheduleOverrides()->where('source', 'legacy_sync')->delete();

            return;
        }

        $batchSchedules = ($student->hte_id && $student->academic_section_id)
            ? HteBatchSchedule::query()
                ->where('hte_id', $student->hte_id)
                ->where('academic_section_id', $student->academic_section_id)
                ->get()
                ->keyBy('day_of_week')
            : collect();

        foreach (self::WEEKDAYS as $day) {
            $isWorking = in_array($day, $workDays, true);
            $batch = $batchSchedules->get($day);
            $matchesBatch = $batch
                && (bool) $batch->is_working === $isWorking
                && (! $isWorking || (
                    self::sameTime($batch->am_start, $student->official_am_start)
                    && self::sameTime($batch->am_end, $student->official_am_end)
                    && self::sameTime($batch->pm_start, $student->official_pm_start)
                    && self::sameTime($batch->pm_end, $student->official_pm_end)
                ));

            $existing = StudentScheduleOverride::where('student_internship_id', $internship->id)
                ->where('day_of_week', $day)
                ->first();

            if ($matchesBatch) {
                if ($existing?->source === 'legacy_sync') {
                    $existing->delete();
                }

                continue;
            }

            if ($existing && $existing->source !== 'legacy_sync') {
                continue;
            }

            StudentScheduleOverride::updateOrCreate(
                ['student_internship_id' => $internship->id, 'day_of_week' => $day],
                [
                    'is_working' => $isWorking,
                    'am_start' => $isWorking ? $student->official_am_start : null,
                    'am_end' => $isWorking ? $student->official_am_end : null,
                    'pm_start' => $isWorking ? $student->official_pm_start : null,
                    'pm_end' => $isWorking ? $student->official_pm_end : null,
                    'reason' => 'Synchronized from the existing student schedule.',
                    'source' => 'legacy_sync',
                ],
            );
        }
    }

    private static function sameTime(?string $left, ?string $right): bool
    {
        return $left !== null && $right !== null
            && substr($left, 0, 5) === substr($right, 0, 5);
    }
}
