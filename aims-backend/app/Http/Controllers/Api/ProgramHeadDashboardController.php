<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\ApprovalRecord;
use App\Models\Attendance;
use App\Models\InternshipRequirement;
use App\Models\Student;
use App\Models\TravelLog;
use App\Models\TravelReport;
use App\Support\AttendanceHours;
use App\Support\ProgramAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramHeadDashboardController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'academic_section_id' => ['nullable', 'integer', Rule::exists('academic_sections', 'id')->where(fn ($query) => $query
                ->where('program_id', $programId)
                ->where('is_active', true))],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:30'],
        ]);
        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null;
        $trendTo = $to ?? now()->endOfDay();
        $sectionId = isset($filters['academic_section_id']) ? (int) $filters['academic_section_id'] : null;
        $sections = AcademicSection::where('program_id', $programId)
            ->where('is_active', true)
            ->newestPeriodFirst()
            ->orderBy('year_level')
            ->orderBy('name')
            ->get(['id', 'program_id', 'academic_period_id', 'name', 'year_level']);
        $students = Student::with(['program', 'academicSection', 'hte', 'supervisor:id,role_id', 'attendance', 'requirements'])
            ->forProgram($programId)
            ->when($sectionId, fn ($query) => $query->forAcademicSection($sectionId))
            ->when($filters['academic_year'] ?? null, fn ($query, $value) => $query->forAcademicYear($value))
            ->when($filters['semester'] ?? null, fn ($query, $value) => $query->forSemester($value))
            ->get();
        $studentIds = $students->pluck('id');
        $monthAttendance = Attendance::query()
            ->when($from, fn ($query, Carbon $date) => $query->whereDate('date', '>=', $date->toDateString()))
            ->when($to, fn ($query, Carbon $date) => $query->whereDate('date', '<=', $date->toDateString()))
            ->forStudents($studentIds)
            ->get();
        $recentAttendance = Attendance::whereDate('date', '>=', $trendTo->copy()->subDays(6)->toDateString())
            ->whereDate('date', '<=', $trendTo->toDateString())
            ->forStudents($studentIds)
            ->get();
        $requirements = InternshipRequirement::activeDefinitionOrLegacy()
            ->whereIn('student_id', $studentIds)
            ->get();
        $monthTravel = TravelLog::query()
            ->when($from, fn ($query, Carbon $date) => $query->where('start_time', '>=', $date))
            ->when($to, fn ($query, Carbon $date) => $query->where('start_time', '<=', $date))
            ->whereHas('participants', fn ($query) => $query->whereIn('students.id', $studentIds))
            ->get();

        $present = $monthAttendance->where('status', 'present')->count();
        $late = $monthAttendance->where('status', 'late')->count();
        $absent = $monthAttendance->where('status', 'absent')->count();
        $holiday = $monthAttendance->where('status', 'holiday')->count();
        $workdays = $present + $late + $absent;
        $attendanceRate = $workdays > 0 ? (($present + $late) / $workdays) * 100 : 0;

        $approvedRequirements = $requirements->where('status', 'approved')->count();
        $submittedRequirements = $requirements->whereNotNull('file_path');
        $pendingRequirements = $submittedRequirements->where('status', 'pending')->count();
        $requirementTotal = $requirements->count();

        $pendingAttendance = $monthAttendance
            ->where('status', '!=', 'holiday')
            ->filter(fn (Attendance $record) => ! $record->is_verified)
            ->count();
        $pendingJournals = $monthAttendance
            ->filter(fn (Attendance $record) => $record->journal_status === 'pending'
                && (filled($record->am_activity) || filled($record->pm_activity)))
            ->count();

        $programPerformance = $students
            ->groupBy(fn (Student $student) => $student->program?->code ?? 'Unassigned')
            ->map(function ($programStudents, string $program) {
                $attendance = $programStudents->flatMap->attendance;
                $present = $attendance->whereIn('status', ['present', 'late'])->count();
                $workdays = $present + $attendance->where('status', 'absent')->count();
                $progress = $programStudents->avg(function (Student $student) {
                    $required = max((float) $student->required_ojt_hours, 0);
                    $rendered = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));

                    return $required > 0 ? min(($rendered / $required) * 100, 100) : 0;
                });

                return [
                    'program' => $program,
                    'students' => $programStudents->count(),
                    'active' => $programStudents->where('internship_status', 'active')->count(),
                    'progress' => round($progress, 1),
                    'attendance_rate' => $workdays > 0 ? round(($present / $workdays) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('students')
            ->values();

        $sectionPerformance = $students
            ->groupBy(fn (Student $student) => $student->section ?: 'Unassigned')
            ->map(function ($sectionStudents, string $section) {
                $attendance = $sectionStudents->flatMap->attendance;
                $present = $attendance->whereIn('status', ['present', 'late'])->count();
                $workdays = $present + $attendance->where('status', 'absent')->count();
                $progress = $sectionStudents->avg(function (Student $student) {
                    $required = max((float) $student->required_ojt_hours, 0);
                    $rendered = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));

                    return $required > 0 ? min(($rendered / $required) * 100, 100) : 0;
                });

                return [
                    'section' => $section,
                    'students' => $sectionStudents->count(),
                    'active' => $sectionStudents->where('internship_status', 'active')->count(),
                    'progress' => round($progress, 1),
                    'attendance_rate' => $workdays > 0 ? round(($present / $workdays) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('students')
            ->values();

        $attendanceTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($recentAttendance, $trendTo) {
            $date = $trendTo->copy()->subDays($daysAgo);
            $records = $recentAttendance->filter(fn (Attendance $record) => $record->date->isSameDay($date));
            $present = $records->whereIn('status', ['present', 'late'])->count();
            $workdays = $present + $records->where('status', 'absent')->count();

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'rate' => $workdays > 0 ? round(($present / $workdays) * 100, 1) : 0,
                'records' => $records->count(),
            ];
        })->values();

        $activeStudents = $students->where('internship_status', 'active');
        $averageProgress = $activeStudents->avg(function (Student $student) {
            $required = max((float) $student->required_ojt_hours, 0);
            $rendered = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));

            return $required > 0 ? min(($rendered / $required) * 100, 100) : 0;
        }) ?? 0;

        $deploymentApprovals = ApprovalRecord::query()
            ->where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)
            ->whereIn('subject_id', $students->pluck('id'))
            ->with([
                'submitter:id,role_id',
                'submitter.roleDefinition:id,code',
                'submitter.employeeProfile:id,user_id,first_name,middle_name,last_name',
                'decisionMaker:id,role_id',
                'decisionMaker.roleDefinition:id,code',
                'decisionMaker.employeeProfile:id,user_id,first_name,middle_name,last_name',
            ])
            ->latest('id')
            ->get()
            ->groupBy('subject_id');
        $deploymentReviews = $students->whereNotNull('hte_id')->map(function (Student $student) use ($deploymentApprovals) {
            $approval = $deploymentApprovals->get($student->id, collect())
                ->first(fn (ApprovalRecord $record) => $record->submitter?->role === 'coordinator');
            $approved = $student->requirements->where('status', 'approved')->count();

            return [
                'approval_id' => $approval?->id,
                'student_id' => $student->student_id,
                'student' => trim($student->first_name.' '.$student->last_name),
                'hte' => $student->hte?->name,
                'batch' => $student->academicSection?->name ?? $student->section,
                'proposed_date' => $student->ojt_start_date?->toDateString(),
                'working_schedule' => $this->studentSchedule($student),
                'requirements_readiness' => $student->requirements->count() > 0 && $approved === $student->requirements->count() ? 'Ready' : $approved.'/'.$student->requirements->count().' approved',
                'submitted_by' => $approval?->submitter?->name,
                'submitted_at' => $approval?->submitted_at?->toIso8601String() ?? $approval?->created_at?->toIso8601String(),
                'review_status' => $approval?->status ?? 'not_submitted',
                'deployment_stage' => $approval?->deployment_stage,
                'coordinator_remarks' => null,
                'program_head_remarks' => $approval?->decisionMaker?->role === 'program_head' ? $approval->remarks : null,
            ];
        })->values();

        $travelReviews = TravelReport::query()
            ->whereHas('travelLog.participants', fn ($query) => $query->whereIn('students.id', $studentIds))
            ->whereHas('travelLog', fn ($query) => $query
                ->when($from, fn ($travelQuery, Carbon $date) => $travelQuery->where('start_time', '>=', $date))
                ->when($to, fn ($travelQuery, Carbon $date) => $travelQuery->where('start_time', '<=', $date)))
            ->with(['travelLog.hte:id,name', 'travelLog.participants:id,first_name,last_name', 'travelLog.checkpoints', 'validations'])
            ->latest('submitted_at')
            ->get()
            ->map(function (TravelReport $report) {
                $travel = $report->travelLog;
                $review = $report->validations->where('reviewer_role', 'program_head')->sortByDesc('reviewed_at')->first();

                return [
                    'report_id' => $report->id,
                    'reference' => $report->report_number,
                    'students' => $travel->participants->map(fn (Student $student) => trim($student->first_name.' '.$student->last_name))->implode(', '),
                    'destination' => $travel->hte?->name ?: $travel->destination,
                    'departure' => $travel->departed_at?->format('M j, Y g:i A') ?? $travel->start_time?->format('M j, Y g:i A'),
                    'checkpoints' => $travel->checkpoints->sortBy('sequence')->pluck('checkpoint_name')->filter()->implode(', '),
                    'arrival' => $travel->arrived_at?->format('M j, Y g:i A') ?? $travel->end_time?->format('M j, Y g:i A'),
                    'route_summary' => $travel->route_notes,
                    'photo_evidence' => filled($travel->arrival_photo_path) || $travel->checkpoints->contains(fn ($checkpoint) => filled($checkpoint->photo_path)),
                    'coordinator_remarks' => $report->remarks,
                    'submitted_at' => $report->submitted_at?->toIso8601String(),
                    'review_status' => $report->status,
                    'program_head_remarks' => $review?->comments ?: $review?->reason,
                ];
            })->values();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'date_from' => $from?->toDateString(),
                'date_to' => $to?->toDateString(),
                'academic_section_id' => $sectionId,
                'academic_year' => $filters['academic_year'] ?? null,
                'semester' => $filters['semester'] ?? null,
            ],
            'scope' => [
                'college' => $request->user()->college,
                'program' => $request->user()->program,
                'sections' => $sections,
                'academic_years' => $students->pluck('internship_year')->filter()->unique()->sortDesc()->values(),
                'semesters' => $students->pluck('internship_semester')->filter()->unique()->sort()->values(),
                'selected_section' => $sectionId ? $sections->firstWhere('id', $sectionId) : null,
            ],
            'overview' => [
                'total_students' => $students->count(),
                'active' => $activeStudents->count(),
                'completed' => $students->where('internship_status', 'completed')->count(),
                'pending' => $students->where('internship_status', 'pending')->count(),
                'deployed' => $students->whereNotNull('hte_id')->count(),
                'average_progress' => round($averageProgress, 1),
            ],
            'attendance' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'holiday' => $holiday,
                'total' => $monthAttendance->count(),
                'rate' => round($attendanceRate, 1),
                'verified' => $monthAttendance->where('is_verified', true)->count(),
                'total_hours' => round($monthAttendance->sum(fn (Attendance $record) => $this->renderedHours($record)), 1),
                'trend' => $attendanceTrend,
            ],
            'requirements' => [
                'total' => $requirementTotal,
                'submitted' => $submittedRequirements->count(),
                'approved' => $approvedRequirements,
                'pending' => $pendingRequirements,
                'rejected' => $requirements->where('status', 'rejected')->count(),
                'missing' => $requirements->whereNull('file_path')->count(),
                'completion_rate' => $requirementTotal > 0 ? round(($approvedRequirements / $requirementTotal) * 100, 1) : 0,
            ],
            'travel' => [
                'active' => TravelLog::whereIn('status', ['active', 'traveling', 'arrived'])
                    ->whereHas('participants', fn ($query) => $query->whereIn('students.id', $studentIds))
                    ->count(),
                'total_this_month' => $monthTravel->count(),
                'completed_this_month' => $monthTravel->where('status', 'completed')->count(),
                'cancelled_this_month' => $monthTravel->where('status', 'cancelled')->count(),
                'completion_rate' => $monthTravel->count() > 0
                    ? round(($monthTravel->where('status', 'completed')->count() / $monthTravel->count()) * 100, 1)
                    : 0,
            ],
            'pending_reviews' => [
                'requirements' => $pendingRequirements,
                'attendance' => $pendingAttendance,
                'journals' => $pendingJournals,
                'total' => $pendingRequirements + $pendingAttendance + $pendingJournals,
            ],
            'deployment_reviews' => $deploymentReviews,
            'travel_reviews' => $travelReviews,
            'analytics' => [
                'average_progress' => round($averageProgress, 1),
                'internship_status' => [
                    ['label' => 'Active', 'value' => $activeStudents->count(), 'color' => '#059669'],
                    ['label' => 'Completed', 'value' => $students->where('internship_status', 'completed')->count(), 'color' => '#2563eb'],
                    ['label' => 'Pending', 'value' => $students->where('internship_status', 'pending')->count(), 'color' => '#d97706'],
                    ['label' => 'Dropped', 'value' => $students->where('internship_status', 'dropped')->count(), 'color' => '#dc2626'],
                ],
                'program_performance' => $programPerformance,
                'section_performance' => $sectionPerformance,
            ],
        ]);
    }

    private function renderedHours(Attendance $attendance): float
    {
        return AttendanceHours::total($attendance);
    }

    private function studentSchedule(Student $student): string
    {
        $days = collect($student->work_days ?? [])->map(fn (string $day) => ucfirst(substr($day, 0, 3)))->implode(', ');

        return collect([
            $days ?: null,
            $student->official_am_start && $student->official_am_end ? 'AM '.$student->official_am_start.'-'.$student->official_am_end : null,
            $student->official_pm_start && $student->official_pm_end ? 'PM '.$student->official_pm_start.'-'.$student->official_pm_end : null,
        ])->filter()->implode(' | ') ?: 'Not configured';
    }
}
