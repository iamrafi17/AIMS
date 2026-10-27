<?php

namespace App\Support;

use App\Models\AcademicSection;
use App\Models\Evaluation;
use App\Models\StudentInternship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EvaluationAccess
{
    public static function section(StudentInternship $internship): ?AcademicSection
    {
        // The linked enrollment is the only fallback; never use today's student profile.
        return $internship->academicSection ?? $internship->enrollment?->academicSection;
    }

    public static function assigned(User $user): Builder
    {
        return StudentInternship::query()->whereHas('currentPlacement.supervisor', fn ($q) => $q
            ->where('is_active', true)->whereHas('supervisorProfile', fn ($p) => $p->where('user_id', $user->id)));
    }

    public static function authorizeEvaluator(User $user, Evaluation $evaluation, bool $editing = false): void
    {
        abort_unless($user->role === 'supervisor' && (int) $evaluation->supervisor_id === $user->id, 404);
        if ($editing || ($evaluation->evaluation_type === 'whole_semester' && $evaluation->status === 'draft')) {
            abort_unless(self::assigned($user)->whereKey($evaluation->student_internship_id)->exists(), 404, 'The internship is no longer assigned to you.');
        }
    }

    public static function visibleSections(User $user): Builder
    {
        $query = AcademicSection::query();
        if ($user->role === 'dean') {
            abort_unless($user->normalizedCollegeId(), 403, 'No college is assigned to this account.');

            return $query->whereHas('program', fn ($p) => $p->where('college_id', $user->normalizedCollegeId()));
        }
        $query->where('program_id', ProgramAccess::programId($user));
        if ($user->role === 'coordinator') {
            // History includes assigned inactive sections as well as current ones.
            $query->whereIn('id', DB::table('coordinator_section_assignments')->where('coordinator_id', $user->id)->select('academic_section_id'));
        }

        return $query;
    }

    public static function resultsInternships(User $user): Builder
    {
        $sections = self::visibleSections($user)->select('academic_sections.id');

        return StudentInternship::query()->where(fn ($q) => $q->whereIn('academic_section_id', $sections)
            ->orWhere(fn ($fallback) => $fallback->whereNull('academic_section_id')->whereHas('enrollment', fn ($e) => $e->whereIn('academic_section_id', clone $sections))));
    }
}
