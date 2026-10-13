<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\OjtEnrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ProgramAccess
{
    public static function programId(User $user): int
    {
        abort_unless(
            in_array($user->role, ['coordinator', 'program_head'], true),
            403,
            'This account does not have program-scoped access.'
        );

        $programId = $user->normalizedProgramId();
        $collegeId = $user->normalizedCollegeId();

        abort_unless(
            $collegeId && $programId,
            403,
            'No academic program is assigned to this account. Ask an administrator to assign a college and program.'
        );

        $validAssignment = Program::query()
            ->whereKey($programId)
            ->where('college_id', $collegeId)
            ->exists();

        abort_unless($validAssignment, 403, 'The academic program assignment for this account is invalid.');

        return $programId;
    }

    public static function studentQuery(User $user): Builder
    {
        $programId = self::programId($user);
        $query = Student::query()->forProgram($programId);
        return $user->role === 'coordinator'
            ? $query->whereHas('currentInternship', fn (Builder $internship) => $internship->whereIn('student_internships.academic_section_id', self::sectionIds($user)))
            : $query;
    }

    public static function sectionIds(User $user): array
    {
        $programId = self::programId($user);
        return DB::table('coordinator_section_assignments as assignments')
            ->join('academic_sections as sections', 'sections.id', '=', 'assignments.academic_section_id')
            ->where('assignments.coordinator_id', $user->id)
            ->where('sections.program_id', $programId)
            ->where('sections.is_active', true)
            ->pluck('sections.id')->map(fn ($id) => (int) $id)->all();
    }

    public static function sectionQuery(User $user): Builder
    {
        $programId = self::programId($user);
        $query = \App\Models\AcademicSection::query()->where('program_id', $programId);
        return $user->role === 'coordinator' ? $query->whereIn('id', self::sectionIds($user)) : $query;
    }

    public static function enrollmentQuery(User $user): Builder
    {
        $query = OjtEnrollment::query()->forProgram(self::programId($user));
        return $user->role === 'coordinator'
            ? $query->whereIn('academic_section_id', self::sectionIds($user)) : $query;
    }

    public static function authorizeSection(User $user, int $sectionId): void
    {
        abort_unless(in_array($sectionId, self::sectionIds($user), true), 404);
    }

    public static function authorizeStudent(User $user, Student $student): void
    {
        abort_unless((int) $student->program_id === self::programId($user), 404);
        if ($user->role === 'coordinator') {
            abort_unless(self::studentQuery($user)->whereKey($student->id)->exists(), 404);
        }
    }

    public static function authorizeAttendance(User $user, Attendance $attendance): void
    {
        $attendance->loadMissing('student');
        abort_unless($attendance->student, 404);
        self::authorizeStudent($user, $attendance->student);
    }

    public static function authorizeRequirement(User $user, InternshipRequirement $requirement): void
    {
        $requirement->loadMissing('student');
        abort_unless((int) $requirement->student?->program_id === self::programId($user), 404);
    }

    public static function authorizeTravel(User $user, TravelLog $travel): void
    {
        $programId = self::programId($user);
        $travel->loadMissing('hte:id');

        abort_unless(
            $travel->hte?->programs()->whereKey($programId)->wherePivot('is_active', true)->exists()
            && $travel->participants()->forProgram($programId)->exists(),
            404,
        );
    }

    public static function authorizeCheckpoint(User $user, TravelCheckpoint $checkpoint): void
    {
        $checkpoint->loadMissing('travelLog');
        abort_unless($checkpoint->travelLog, 404);
        self::authorizeTravel($user, $checkpoint->travelLog);
    }

    public static function authorizeHte(User $user, HTE $hte): void
    {
        abort_unless(
            $hte->programs()
                ->whereKey(self::programId($user))
                ->wherePivot('is_active', true)
                ->exists(),
            404,
        );
    }

    public static function authorizeHoliday(User $user, Holiday $holiday): void
    {
        abort_unless((int) $holiday->program_id === self::programId($user), 404);
    }

    public static function authorizeMoa(User $user, MOA $moa): void
    {
        abort_unless((int) $moa->program_id === self::programId($user), 404);
    }
}
