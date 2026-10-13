<?php

namespace App\Support;

use App\Models\MOA;
use App\Models\Student;
use App\Models\TravelLog;
use App\Models\User;

final class DeanAccess
{
    public static function collegeId(User $dean): int
    {
        abort_unless($dean->role === 'dean' && $dean->is_active && $dean->normalizedCollegeId(), 403, 'An active Dean college assignment is required.');

        return $dean->normalizedCollegeId();
    }

    public static function student(User $dean, Student $student): void
    {
        abort_unless(Student::query()->forCollege(self::collegeId($dean))->whereKey($student->id)->exists(), 404);
    }

    public static function moa(User $dean, MOA $moa): void
    {
        abort_unless($moa->program()->where('college_id', self::collegeId($dean))->exists(), 404);
    }

    public static function travel(User $dean, TravelLog $travel): void
    {
        $collegeId = self::collegeId($dean);
        abort_unless($travel->participants()->exists()
            && ! $travel->participants()->whereDoesntHave('currentInternship.academicSection.program', fn ($program) => $program->where('college_id', $collegeId))->exists(), 404);
    }
}
