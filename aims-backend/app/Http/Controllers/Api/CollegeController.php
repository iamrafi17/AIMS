<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\College;
use App\Models\HTE;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollegeController extends Controller
{
    public function index()
    {
        $colleges = College::where('is_active', true)->get();

        return response()->json($colleges);
    }

    public function show(College $college)
    {
        return response()->json($college);
    }

    public function programs(College $college)
    {
        $programs = $college->programs()->where('is_active', true)->get();

        return response()->json($programs);
    }

    public function htes(Request $request, College $college)
    {
        $request->validate(['academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id']]);
        $programId = $request->integer('program_id');
        $periodId = $request->integer('academic_period_id') ?: AcademicPeriod::where('status', 'active')->value('id');
        $htes = HTE::where('is_active', true)
            ->whereHas('programs', fn ($query) => $query
                ->where('programs.college_id', $college->id)
                ->when($programId, fn ($programQuery) => $programQuery->whereKey($programId))
                ->where('hte_programs.is_active', true))
            ->with(['departments' => fn ($query) => $query
                ->whereHas('programs', fn ($programs) => $programs
                    ->where('programs.college_id', $college->id)
                    ->when($programId, fn ($programQuery) => $programQuery->whereKey($programId))
                    ->where('hte_department_programs.is_active', true))
                ->with('supervisors.supervisorProfile:id,full_name')
                ->orderBy('department_name')])
            ->orderBy('name')
            ->get();

        $departmentIds = $htes->pluck('departments')->flatten(1)->pluck('id');
        $counts = $periodId && $departmentIds->isNotEmpty()
            ? DB::table('student_internships as preferences')
                ->join('academic_sections as sections', 'sections.id', '=', 'preferences.academic_section_id')
                ->join('ojt_enrollments as registrations', function ($join) {
                    $join->on('registrations.id', '=', 'preferences.ojt_enrollment_id')
                        ->on('registrations.student_record_id', '=', 'preferences.student_id');
                })
                ->whereNotNull('registrations.registered_at')
                ->where('sections.academic_period_id', $periodId)
                ->whereIn('preferences.preferred_hte_department_id', $departmentIds)
                ->select('preferences.preferred_hte_id', 'preferences.preferred_hte_department_id')
                ->selectRaw('COUNT(DISTINCT preferences.student_id) as students_selected')
                ->groupBy('preferences.preferred_hte_id', 'preferences.preferred_hte_department_id')
                ->get()->keyBy(fn ($count) => $count->preferred_hte_id.':'.$count->preferred_hte_department_id)
            : collect();

        foreach ($htes as $hte) {
            foreach ($hte->departments as $department) {
                $department->setAttribute('students_selected', (int) ($counts->get($hte->id.':'.$department->id)?->students_selected ?? 0));
                $department->setRelation('supervisors', $department->supervisors->map(fn ($supervisor) => [
                    'id' => $supervisor->id,
                    'full_name' => $supervisor->full_name,
                ]));
            }
        }

        return response()->json($htes);
    }
}
