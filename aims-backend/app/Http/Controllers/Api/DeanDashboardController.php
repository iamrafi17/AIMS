<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\AcademicPeriod;
use App\Models\Attendance;
use App\Models\MOA;
use App\Models\Program;
use App\Models\StudentInternship;
use App\Models\StudentLiveLocation;
use App\Models\TravelReport;
use App\Support\AttendanceHours;
use App\Support\DeanAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DeanDashboardController extends Controller
{
    public function index(Request $request)
    {
        $collegeId = DeanAccess::collegeId($request->user());
        $validated = $request->validate([
            'academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
        ]);
        $programs = Program::query()->where('college_id', $collegeId)->orderBy('code')->get(['id', 'name', 'code']);
        $programId = isset($validated['program_id']) ? (int) $validated['program_id'] : null;
        abort_if($programId && ! $programs->contains('id', $programId), 422, 'The selected program is outside your college.');

        $periods = AcademicPeriod::query()->orderByDesc('start_date')->orderByDesc('id')
            ->get(['id', 'academic_year', 'semester', 'status', 'start_date', 'end_date']);
        $activePeriod = $periods->firstWhere('status', 'active');
        $period = isset($validated['academic_period_id'])
            ? $periods->firstWhere('id', (int) $validated['academic_period_id'])
            : ($activePeriod ?? $periods->first());
        $periodId = $period?->id;

        $internships = StudentInternship::query()
            ->whereHas('academicSection', fn (Builder $section) => $section
                ->where('academic_period_id', $periodId)
                ->whereHas('program', fn (Builder $program) => $program->where('college_id', $collegeId)
                    ->when($programId, fn (Builder $query) => $query->whereKey($programId))));
        // An active term represents the current cohort. Completed terms retain their own history.
        if ($period?->status === 'active') {
            $internships->where('is_current', true);
        }
        $records = $periodId ? (clone $internships)->with('academicSection.program')->get() : collect();
        $internshipIds = $records->pluck('id');
        $studentIds = $records->pluck('student_id')->unique();
        $attendance = $internshipIds->isEmpty() ? collect() : Attendance::query()
            ->whereIn('student_internship_id', $internshipIds)
            ->whereIn('status', ['present', 'late'])
            ->whereHas('reviews', fn (Builder $review) => $review
                ->where('review_type', 'verification')->where('decision', 'approved'))
            ->with(['events', 'scheduleSnapshots', 'reviews', 'internship.student'])
            ->get();
        $hoursByInternship = $attendance->groupBy('student_internship_id')
            ->map(fn ($days) => $days->sum(fn (Attendance $day) => AttendanceHours::total($day, $day->internship?->student)));

        $statusCounts = $records->unique('student_id')->groupBy(fn ($record) => $record->status ?: 'other');
        $statusChart = $statusCounts->map(fn ($group, $status) => [
            'status' => $status, 'count' => $group->count(),
            'percentage' => $studentIds->count() ? round($group->count() * 100 / $studentIds->count(), 1) : 0,
        ])->values();
        $progress = $records->groupBy('academicSection.program_id')->map(function ($group) use ($hoursByInternship) {
            $program = $group->first()->academicSection->program;
            $students = $group->unique('student_id');
            return [
                'program_id' => $program->id,
                'program' => $program->code,
                'name' => $program->name,
                'interns' => $students->count(),
                'average_progress' => round($students->avg(function ($record) use ($hoursByInternship) {
                    $required = (float) $record->required_hours;
                    return $required > 0 ? min(100, (float) ($hoursByInternship[$record->id] ?? 0) / $required * 100) : 0;
                }) ?? 0, 1),
            ];
        })->sortBy('program')->values();
        $monthly = $attendance->unique(fn (Attendance $day) => $day->internship?->student_id.'|'.$day->date->toDateString())
            ->groupBy(fn (Attendance $day) => $day->date->format('Y-m'))
            ->map(fn ($days, $month) => ['month' => $month, 'days' => $days->count()])
            ->sortKeys()->values();

        $canReview = $period && $activePeriod && $period->id === $activePeriod->id;
        $deploymentCount = $canReview && $studentIds->isNotEmpty()
            ? ApprovalRecord::query()->where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)
                ->where('deployment_stage', \App\Support\DeploymentWorkflow::DEAN)
                ->whereIn('deployment_internship_id', $internshipIds)->count() : 0;
        $moaCount = $canReview ? MOA::query()->whereIn('program_id', $programId ? [$programId] : $programs->pluck('id'))
            ->where('program_status', 'endorsed')->where('status', 'pending')->count() : 0;
        $travelCount = $canReview && $studentIds->isNotEmpty() ? TravelReport::query()
            ->where('status', 'pending_vpaa')
            ->whereHas('travelLog.participants', fn (Builder $student) => $student->whereIn('students.id', $studentIds))
            ->whereDoesntHave('travelLog.participants', fn (Builder $student) => $student
                ->whereNotIn('students.id', $studentIds))->count() : 0;
        $summary = [
            'total_students' => $studentIds->count(),
            'active_interns' => $records->where('status', 'active')->unique('student_id')->count(),
            'pending_deployments' => $deploymentCount,
            'pending_moas' => $moaCount,
            'pending_travel_reports' => $travelCount,
        ];

        return response()->json([
            'college' => $request->user()->employeeProfile?->college?->only(['id', 'name', 'code']),
            'filters' => ['academic_period_id' => $periodId, 'program_id' => $programId,
                'academic_periods' => $periods, 'programs' => $programs, 'can_review' => (bool) $canReview],
            'summary' => $summary,
            'charts' => ['internship_status' => $statusChart, 'program_progress' => $progress,
                'monthly_attendance' => $monthly],
            'pending_actions' => [
                ['key' => 'deployments', 'label' => 'Deployment Forwarding', 'count' => $deploymentCount, 'path' => '/dean/deployments'],
                ['key' => 'moas', 'label' => 'MOA Final Approval', 'count' => $moaCount, 'path' => '/dean/moas'],
                ['key' => 'travel', 'label' => 'Travel Validation', 'count' => $travelCount, 'path' => '/dean/travel'],
            ],
            // Existing clients use these flat fields.
            'students' => $summary['total_students'],
            'active_students' => $summary['active_interns'],
            'pending_deployments' => $deploymentCount,
            'pending_moas' => $moaCount,
            'pending_travel_reports' => $travelCount,
        ]);
    }

    public function liveLocations(Request $request)
    {
        $collegeId = DeanAccess::collegeId($request->user());
        $locations = StudentLiveLocation::query()
            ->where('captured_at', '>=', now()->subSeconds(60))
            ->whereHas('student', fn (Builder $student) => $student->forCollege($collegeId)
                ->whereHas('user', fn (Builder $user) => $user->where('is_active', true))
                ->whereHas('attendance', fn (Builder $attendance) => $attendance
                    ->whereDate('attendance.date', now()->toDateString())
                    ->whereIn('attendance.status', ['present', 'late'])
                    ->where(fn (Builder $session) => $session
                        ->where(fn (Builder $am) => $am->whereHas('events', fn (Builder $events) => $events->where('event_type', 'am_in'))
                            ->whereDoesntHave('events', fn (Builder $events) => $events->where('event_type', 'am_out')))
                        ->orWhere(fn (Builder $pm) => $pm->whereHas('events', fn (Builder $events) => $events->where('event_type', 'pm_in'))
                            ->whereDoesntHave('events', fn (Builder $events) => $events->where('event_type', 'pm_out'))))))
            ->with(['student:id,user_id,student_id,first_name,last_name', 'student.program:id,name,code', 'student.hte:id,name,address,latitude,longitude,geofence_radius,geofence_enabled'])
            ->latest('captured_at')->get();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'sharing' => $locations->count(),
            'locations' => $locations->map(function (StudentLiveLocation $location) {
                $hte = $location->student->hte;
                $radius = $hte?->geofence_enabled ? (int) $hte->geofence_radius : null;
                $distance = null;
                if ($radius && $hte->latitude !== null && $hte->longitude !== null) {
                    $lat = deg2rad((float) $hte->latitude - (float) $location->latitude);
                    $lon = deg2rad((float) $hte->longitude - (float) $location->longitude);
                    $a = sin($lat / 2) ** 2 + cos(deg2rad((float) $location->latitude)) * cos(deg2rad((float) $hte->latitude)) * sin($lon / 2) ** 2;
                    $distance = (int) round(6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a)));
                }
                return [
                    'id' => $location->id,
                    'student_id' => $location->student->id,
                    'school_id' => $location->student->student_id,
                    'name' => trim($location->student->first_name.' '.$location->student->last_name),
                    'program' => $location->student->program?->code,
                    'hte' => $hte?->name,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'accuracy' => $location->accuracy,
                    'captured_at' => $location->captured_at?->toIso8601String(),
                    'outside_geofence' => $distance !== null && $distance > $radius,
                    'distance_from_hte' => $distance,
                    'geofence_radius' => $radius,
                ];
            })->values(),
        ]);
    }
}
