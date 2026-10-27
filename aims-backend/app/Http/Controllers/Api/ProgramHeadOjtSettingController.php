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
        $settings = ProgramOjtSetting::query()->where('program_id', $programId)
            ->with('academicPeriod:id,academic_year,semester,status')
            ->orderByDesc('published_at')->get();
        $periods = AcademicPeriod::query()->orderByDesc('start_date')->get();
        $periods->each(function (AcademicPeriod $period) use ($programId, $settings): void {
            $reason = $period->status === 'completed'
                ? 'A completed academic period cannot receive a new OJT setup.'
                : $this->deploymentBlockReason($programId, $period->id, $settings->contains('academic_period_id', $period->id));
            $period->setAttribute('publish_block_reason', $reason);
        });

        return response()->json([
            'program' => Program::query()->findOrFail($programId, ['id', 'code', 'name', 'college_id']),
            'periods' => $periods,
            'settings' => $settings,
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

            $publishedSettingExists = ProgramOjtSetting::query()
                ->where('program_id', $programId)
                ->where('academic_period_id', $period->id)
                ->exists();
            if ($reason = $this->deploymentBlockReason($programId, $period->id, $publishedSettingExists)) {
                throw ValidationException::withMessages(['academic_period_id' => $reason]);
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

    private function deploymentBlockReason(int $programId, int $periodId, bool $publishedSettingExists): ?string
    {
        $placements = DB::table('student_internship_placements as placements')
            ->join('student_internships as internships', 'internships.id', '=', 'placements.student_internship_id')
            ->join('academic_sections as sections', 'sections.id', '=', 'internships.academic_section_id')
            ->where('sections.program_id', $programId)
            ->where('sections.academic_period_id', $periodId);

        if ($publishedSettingExists && (clone $placements)->exists()) {
            return 'This OJT setup cannot be changed after a student deployment has used it.';
        }

        if (! $publishedSettingExists && (clone $placements)
                ->where(function ($query): void {
                    $query->where('placements.is_current', true)
                        ->orWhereExists(function ($approval): void {
                            $approval->selectRaw('1')->from('approval_records')
                                ->whereColumn('approval_records.subject_id', 'internships.student_id')
                                ->where('approval_records.subject_type', 'deployment')
                                ->where('approval_records.status', 'approved');
                        })
                        ->orWhereExists(function ($attendance): void {
                            $attendance->selectRaw('1')->from('attendance')
                                ->whereColumn('attendance.student_internship_id', 'internships.id');
                        });
                })->exists()) {
            return 'An active or used deployment exists for this program and period. Resolve it before publishing OJT setup.';
        }

        return null;
    }
}
