<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\Program;
use App\Models\Student;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ProgramAccess
{
    public static function programId(User $user): int
    {
        abort_unless(
            in_array($user->role, ['coordinator', 'program_head'], true),
            403,
            'This account does not have program-scoped access.'
        );

        abort_unless(
            $user->college_id && $user->program_id,
            403,
            'No academic program is assigned to this account. Ask an administrator to assign a college and program.'
        );

        $validAssignment = Program::query()
            ->whereKey($user->program_id)
            ->where('college_id', $user->college_id)
            ->exists();

        abort_unless($validAssignment, 403, 'The academic program assignment for this account is invalid.');

        return (int) $user->program_id;
    }

    public static function studentQuery(User $user): Builder
    {
        return Student::query()->where('program_id', self::programId($user));
    }

    public static function authorizeStudent(User $user, Student $student): void
    {
        abort_unless((int) $student->program_id === self::programId($user), 404);
    }

    public static function authorizeAttendance(User $user, Attendance $attendance): void
    {
        $attendance->loadMissing('student:id,program_id');
        abort_unless((int) $attendance->student?->program_id === self::programId($user), 404);
    }

    public static function authorizeRequirement(User $user, InternshipRequirement $requirement): void
    {
        $requirement->loadMissing('student:id,program_id');
        abort_unless((int) $requirement->student?->program_id === self::programId($user), 404);
    }

    public static function authorizeTravel(User $user, TravelLog $travel): void
    {
        $travel->loadMissing('student:id,program_id');
        abort_unless((int) $travel->student?->program_id === self::programId($user), 404);
    }

    public static function authorizeCheckpoint(User $user, TravelCheckpoint $checkpoint): void
    {
        $checkpoint->loadMissing('travelLog.student:id,program_id');
        abort_unless((int) $checkpoint->travelLog?->student?->program_id === self::programId($user), 404);
    }

    public static function authorizeHte(User $user, HTE $hte): void
    {
        abort_unless((int) $hte->program_id === self::programId($user), 404);
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
