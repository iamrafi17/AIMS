<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AcademicPeriod;
use App\Models\Attendance;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\OjtEnrollment;
use App\Models\Student;
use App\Models\TravelLog;
use App\Support\AttendanceHours;
use App\Support\ProgramAccess;
use App\Support\RequirementOverview;
use Illuminate\Http\Request;

class CoordinatorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $students = ProgramAccess::studentQuery($request->user())->with(['requirements.definition', 'attendance'])->get();
        InternshipRequirement::ensureForStudents($students->pluck('id'));
        $students->load('requirements.definition');
        $period = AcademicPeriod::where('status', 'active')->first(['academic_year', 'semester']);
        $studentRequirements = $students->mapWithKeys(fn (Student $student) => [$student->id => RequirementOverview::forStudent($student->requirements)]);
        $requirementSummary = [
            'completed' => $studentRequirements->where('status', 'completed')->count(),
            'incomplete' => $studentRequirements->where('status', 'incomplete')->count(),
            'for_review' => $studentRequirements->where('status', 'for_review')->count(),
            'needs_resubmission' => $studentRequirements->where('status', 'needs_resubmission')->count(),
        ];
        $activeStudents = $students->filter(fn (Student $student) => $student->internship_status === 'active');
        $today = now()->toDateString();
        $todayAttendance = $activeStudents->flatMap(fn (Student $student) => $student->attendance)
            ->filter(fn (Attendance $attendance) => $attendance->date?->toDateString() === $today);
        $progressBins = ['0_25' => 0, '26_50' => 0, '51_75' => 0, '76_99' => 0, '100' => 0];
        foreach ($activeStudents as $student) {
            $hours = $student->attendance->sum(fn (Attendance $attendance) => $this->renderedHours($attendance));
            $percent = $student->required_ojt_hours > 0 ? min($hours / $student->required_ojt_hours * 100, 100) : 0;
            $bin = $percent >= 100 ? '100' : ($percent > 75 ? '76_99' : ($percent > 50 ? '51_75' : ($percent > 25 ? '26_50' : '0_25')));
            $progressBins[$bin]++;
        }
        $staleAttendance = $activeStudents->filter(fn (Student $student) => ! $student->attendance->where('date', '>=', now()->subDays(7)->toDateString())->count())->count();
        $monthAttendance = Attendance::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->whereHas('student', fn ($query) => $query->whereIn('students.id', $students->pluck('id')))
            ->get();

        $present = $monthAttendance->where('status', 'present')->count();
        $late = $monthAttendance->where('status', 'late')->count();
        $absent = $monthAttendance->where('status', 'absent')->count();
        $holiday = $monthAttendance->where('status', 'holiday')->count();
        $workdayRecords = $present + $late + $absent;
        $attendanceRate = $workdayRecords > 0 ? (($present + $late) / $workdayRecords) * 100 : 0;

        $expectedJournals = $monthAttendance->sum(fn (Attendance $record) => in_array($record->status, ['present', 'late'], true)
            ? ($record->session_type === 'full_day' ? 2 : 1)
            : 0);
        $submittedJournals = $monthAttendance->sum(function (Attendance $record) {
            if (! in_array($record->status, ['present', 'late'], true)) {
                return 0;
            }

            return (blank($record->am_activity) ? 0 : 1) + (blank($record->pm_activity) ? 0 : 1);
        });

        $requirements = InternshipRequirement::activeDefinitionOrLegacy()
            ->whereHas('student', fn ($query) => $query->forProgram($programId))
            ->get();
        $approvedRequirements = $requirements->where('status', 'approved')->count();
        $requirementTotal = $requirements->count();

        $moas = MOA::with('hte')->where('program_id', $programId)->get();
        $approvedMoas = $moas->filter(fn (MOA $moa) => $moa->status === 'approved' && $moa->expiration_date->gte(now()->startOfDay()));
        $expiredMoas = $moas->filter(fn (MOA $moa) => $moa->expiration_date->lt(now()->startOfDay()));
        $expiringMoas = $approvedMoas->filter(fn (MOA $moa) => $moa->expiration_date->lte(now()->addDays(60)->endOfDay()));

        $monthTravel = TravelLog::whereMonth('start_time', now()->month)
            ->whereYear('start_time', now()->year)
            ->whereHas('participants', fn ($query) => $query->whereIn('students.id', $students->pluck('id')))
            ->get();
        $activeTravel = TravelLog::whereIn('status', ['active', 'traveling', 'arrived'])
            ->whereHas('participants', fn ($query) => $query->whereIn('students.id', $students->pluck('id')))
            ->count();

        $activeInterns = ProgramAccess::studentQuery($request->user())->with(['college', 'program', 'hte', 'attendance', 'requirements.definition'])
            ->forInternshipStatus('active')
            ->orderBy('last_name')
            ->limit(8)
            ->get()
            ->map(function (Student $student) {
                $hours = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));
                $requiredHours = max((float) $student->required_ojt_hours, 0);
                $lastAttendance = $student->attendance->sortByDesc('date')->first();
                $activeRequirements = $student->requirements->filter(
                    fn (InternshipRequirement $requirement) => ! $requirement->program_requirement_id || $requirement->definition?->is_active
                );
                $requirementCount = $activeRequirements->count();
                $approved = $activeRequirements->where('status', 'approved')->count();

                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => trim($student->first_name.' '.$student->last_name),
                    'program' => $student->program?->code,
                    'hte' => $student->hte?->name ?? 'Not assigned',
                    'rendered_hours' => round($hours, 1),
                    'required_hours' => round($requiredHours, 1),
                    'progress_percent' => $requiredHours > 0 ? round(min(($hours / $requiredHours) * 100, 100), 1) : 0,
                    'last_attendance_date' => $lastAttendance?->date?->toDateString(),
                    'last_attendance_status' => $lastAttendance?->status ?? 'no_record',
                    'requirements_percent' => $requirementCount > 0 ? round(($approved / $requirementCount) * 100, 1) : 0,
                ];
            })->values();

        $awaitingRegistration = ProgramAccess::enrollmentQuery($request->user())->whereNull('registered_at')->count();
        $missingJournals = max($expectedJournals - $submittedJournals, 0);
        $pendingRequirements = $requirements->filter(fn (InternshipRequirement $requirement) => $requirement->file_path && $requirement->status === 'pending')->count();
        $actionCenter = collect([
            ['title' => 'Pending student registrations', 'count' => $awaitingRegistration, 'link' => '/coordinator/students?registration=pending#ojt-enrollments'],
            ['title' => 'Students with incomplete requirements', 'count' => $requirementSummary['incomplete'], 'link' => '/coordinator/internship-requirements?status=incomplete'],
            ['title' => 'Students without attendance for 7+ days', 'count' => $staleAttendance, 'link' => '/coordinator/attendance?view=attendance'],
            ['title' => 'MOAs nearing expiration', 'count' => $expiringMoas->count(), 'link' => '/coordinator/moas?validity=expiring%20soon'],
            ['title' => 'Missing journal submissions', 'count' => $missingJournals, 'link' => '/coordinator/attendance?view=journals'],
        ])->filter(fn ($item) => $item['count'] > 0)->values();
        $alerts = $actionCenter->map(fn ($item) => [
            'type' => 'warning', 'title' => $item['title'], 'message' => $item['count'].' requiring attention',
            'count' => $item['count'], 'link' => $item['link'],
        ]);

        $announcements = Announcement::where('is_published', true)
            ->whereIn('target_audience', ['all', 'coordinators'])
            ->latest('published_at')
            ->limit(4)
            ->get(['id', 'title', 'content', 'published_at', 'created_at']);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'academic_period' => $period,
            'action_center' => $actionCenter,
            'internship_status' => [
                'active' => $activeStudents->count(),
                'completed' => $students->where('internship_status', 'completed')->count(),
                'pending' => $students->where('internship_status', 'pending')->count(),
                'dropped' => $students->where('internship_status', 'dropped')->count(),
            ],
            'attendance_today' => [
                'present' => $todayAttendance->where('status', 'present')->count(),
                'late' => $todayAttendance->where('status', 'late')->count(),
                'absent' => $todayAttendance->where('status', 'absent')->count(),
                'no_log' => max($activeStudents->count() - $todayAttendance->pluck('student_id')->unique()->count(), 0),
            ],
            'ojt_progress' => $progressBins,
            'requirement_summary' => $requirementSummary,
            'scope' => [
                'college' => $request->user()->college,
                'program' => $request->user()->program,
            ],
            'overview' => [
                'total_students' => $students->count(),
                'active' => $students->where('internship_status', 'active')->count(),
                'completed' => $students->where('internship_status', 'completed')->count(),
                'pending' => $students->where('internship_status', 'pending')->count(),
                'dropped' => $students->where('internship_status', 'dropped')->count(),
                'awaiting_registration' => $awaitingRegistration,
                'needs_attention' => $actionCenter->sum('count'),
            ],
            'active_interns' => $activeInterns,
            'attendance' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'holiday' => $holiday,
                'rate' => round($attendanceRate, 1),
                'total_hours' => round($monthAttendance->sum(fn (Attendance $record) => $this->renderedHours($record)), 1),
            ],
            'journals' => [
                'submitted' => $submittedJournals,
                'expected' => $expectedJournals,
                'missing' => $missingJournals,
                'students_with_submissions' => $monthAttendance->filter(fn (Attendance $record) => filled($record->am_activity) || filled($record->pm_activity))->pluck('student_id')->unique()->count(),
                'completion_rate' => $expectedJournals > 0 ? round(($submittedJournals / $expectedJournals) * 100, 1) : 0,
            ],
            'requirements' => [
                'total' => $requirementTotal,
                'approved' => $approvedRequirements,
                'pending' => $pendingRequirements,
                'rejected' => $requirements->where('status', 'rejected')->count(),
                'completion_rate' => $requirementTotal > 0 ? round(($approvedRequirements / $requirementTotal) * 100, 1) : 0,
            ],
            'hte_moa' => [
                'total_htes' => HTE::whereHas('programs', fn ($query) => $query->whereKey($programId)->where('hte_programs.is_active', true))->count(),
                'active_htes' => HTE::where('is_active', true)->whereHas('programs', fn ($query) => $query->whereKey($programId)->where('hte_programs.is_active', true))->count(),
                'assigned_htes' => $students->whereNotNull('hte_id')->pluck('hte_id')->unique()->count(),
                'moa_approved' => $approvedMoas->count(),
                'moa_pending' => $moas->where('status', 'pending')->count(),
                'moa_rejected' => $moas->filter(fn (MOA $moa) => $moa->status === 'returned' || $moa->program_status === 'returned')->count(),
                'moa_expired' => $expiredMoas->count(),
                'expiring_soon' => $expiringMoas->map(fn (MOA $moa) => ['id' => $moa->id, 'hte' => $moa->hte?->name, 'expiration_date' => $moa->expiration_date->toDateString()])->values(),
            ],
            'travel' => [
                'active' => $activeTravel,
                'scheduled' => TravelLog::where('status', 'scheduled')->whereHas('participants', fn ($query) => $query->whereIn('students.id', $students->pluck('id')))->count(),
                'current_session_id' => TravelLog::whereIn('status', ['active', 'traveling', 'arrived'])->whereHas('participants', fn ($query) => $query->whereIn('students.id', $students->pluck('id')))->latest('start_time')->value('id'),
                'completed_this_month' => $monthTravel->where('status', 'completed')->count(),
                'cancelled_this_month' => $monthTravel->where('status', 'cancelled')->count(),
                'total_this_month' => $monthTravel->count(),
            ],
            'alerts' => $alerts,
            'announcements' => $announcements,
        ]);
    }

    private function renderedHours(Attendance $attendance): float
    {
        return AttendanceHours::total($attendance);
    }
}
