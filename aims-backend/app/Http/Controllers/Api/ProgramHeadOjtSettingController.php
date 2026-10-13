<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Program;
use App\Models\ProgramOjtSetting;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramHeadOjtSettingController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());

        return response()->json([
            'program' => Program::query()->findOrFail($programId, ['id', 'code', 'name', 'college_id']),
            'periods' => AcademicPeriod::query()->orderByDesc('start_date')->get(),
            'settings' => ProgramOjtSetting::query()->where('program_id', $programId)
                ->with('academicPeriod:id,academic_year,semester,status')
                ->orderByDesc('published_at')->get(),
        ]);
    }

    public function publish(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $data = $request->validate([
            'academic_period_id' => ['required', 'integer', Rule::exists('academic_periods', 'id')],
            'required_hours' => ['required', 'numeric', 'between:1,2000'],
            'ojt_start_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $setting = DB::transaction(function () use ($data, $programId, $request): ProgramOjtSetting {
            $period = AcademicPeriod::query()->lockForUpdate()->findOrFail($data['academic_period_id']);
            if ($period->status === 'completed') {
                throw ValidationException::withMessages(['academic_period_id' => 'A completed academic period cannot receive a new OJT setup.']);
            }

            if (DB::table('student_internship_placements as placements')
                ->join('student_internships as internships', 'internships.id', '=', 'placements.student_internship_id')
                ->join('academic_sections as sections', 'sections.id', '=', 'internships.academic_section_id')
                ->where('sections.program_id', $programId)
                ->where('sections.academic_period_id', $period->id)->exists()) {
                throw ValidationException::withMessages(['academic_period_id' => 'Deployments already exist for this program and period. Publish OJT setup before the first deployment.']);
            }

            return ProgramOjtSetting::query()->updateOrCreate(
                ['program_id' => $programId, 'academic_period_id' => $period->id],
                [
                    'required_hours' => $data['required_hours'],
                    'ojt_start_date' => $data['ojt_start_date'],
                    'status' => 'published',
                    'published_by' => $request->user()->id,
                    'published_at' => now(),
                ],
            );
        });

        return response()->json(['message' => 'OJT setup published.', 'setting' => $setting->load('academicPeriod')]);
    }
}
