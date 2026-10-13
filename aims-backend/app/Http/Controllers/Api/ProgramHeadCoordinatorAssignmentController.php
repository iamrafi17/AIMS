<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\User;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramHeadCoordinatorAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $sections = AcademicSection::query()->where('program_id', $programId)
            ->where('is_active', true)
            ->with('academicPeriod:id,semester,academic_year,status')
            ->withCount(['studentInternships as student_count' => fn ($query) => $query->where('is_current', true)])
            ->newestPeriodFirst()
            ->orderBy('year_level')->orderBy('name')->get();
        $coordinators = User::query()->forRole('coordinator')->where('is_active', true)
            ->forAssignedProgram($programId)->with('employeeProfile:id,user_id,first_name,middle_name,last_name')
            ->orderBy('id')->get(['id', 'username']);
        $assignments = DB::table('coordinator_section_assignments')
            ->whereIn('academic_section_id', $sections->pluck('id'))
            ->pluck('coordinator_id', 'academic_section_id');

        return response()->json([
            'program' => $request->user()->program,
            'periods' => $sections->pluck('academicPeriod')->filter()->unique('id')->values()->map(fn ($period) => [
                'id' => $period->id,
                'semester' => $period->semester,
                'academic_year' => $period->academic_year,
                'status' => $period->status,
            ]),
            'coordinators' => $coordinators->map(fn (User $user) => [
                'id' => $user->id, 'name' => $user->employeeProfile?->full_name,
                'username' => $user->username,
            ]),
            'sections' => $sections->map(fn (AcademicSection $section) => [
                'id' => $section->id,
                'name' => $section->name,
                'year_level' => $section->year_level,
                'academic_year' => $section->academic_year,
                'academic_period_id' => $section->academic_period_id,
                'student_count' => $section->student_count,
                'coordinator_id' => $assignments[$section->id] ?? null,
            ]),
        ]);
    }

    public function update(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $data = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.section_id' => ['required', 'integer', 'distinct'],
            'assignments.*.coordinator_id' => ['nullable', 'integer'],
        ]);
        $rows = collect($data['assignments']);
        $sectionIds = $rows->pluck('section_id');
        $validSections = AcademicSection::query()->where('program_id', $programId)
            ->where('is_active', true)->whereIn('id', $sectionIds)->count();
        if ($validSections !== $sectionIds->count()) {
            throw ValidationException::withMessages(['assignments' => 'A section is outside your active program.']);
        }
        $coordinatorIds = $rows->pluck('coordinator_id')->filter()->unique();
        $validCoordinators = User::query()->forRole('coordinator')->where('is_active', true)
            ->forAssignedProgram($programId)->whereIn('id', $coordinatorIds)->count();
        if ($validCoordinators !== $coordinatorIds->count()) {
            throw ValidationException::withMessages(['assignments' => 'A Coordinator is outside your program or inactive.']);
        }

        DB::transaction(function () use ($rows, $request): void {
            foreach ($rows as $row) {
                $sectionId = (int) $row['section_id'];
                if (empty($row['coordinator_id'])) {
                    DB::table('coordinator_section_assignments')->where('academic_section_id', $sectionId)->delete();
                    continue;
                }
                DB::table('coordinator_section_assignments')->updateOrInsert(
                    ['academic_section_id' => $sectionId],
                    ['coordinator_id' => (int) $row['coordinator_id'],
                        'assigned_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        return $this->index($request);
    }
}
