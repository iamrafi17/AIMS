<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\InternshipRequirement;
use App\Models\Student;
use App\Models\TravelLog;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;

class ProgramHeadDashboardController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $students = Student::with(['program', 'hte', 'attendance', 'requirements'])
            ->where('program_id', $programId)->get();
        $monthAttendance = Attendance::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
            ->get();
        $recentAttendance = Attendance::whereDate('date', '>=', now()->subDays(6)->toDateString())
            ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
            ->get();
        $requirements = InternshipRequirement::activeDefinitionOrLegacy()
            ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
            ->get();
        $monthTravel = TravelLog::whereMonth('start_time', now()->month)
            ->whereYear('start_time', now()->year)
            ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
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

        $pendingRegistrations = $students->where('registration_status', 'pending')->count();
        $pendingAttendance = $monthAttendance
            ->where('status', '!=', 'holiday')
            ->where('is_verified', false)
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

        $attendanceTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($recentAttendance) {
            $date = now()->subDays($daysAgo);
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

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'scope' => [
                'college' => $request->user()->college,
                'program' => $request->user()->program,
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
                'active' => TravelLog::where('status', 'active')
                    ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
                    ->count(),
                'total_this_month' => $monthTravel->count(),
                'completed_this_month' => $monthTravel->where('status', 'completed')->count(),
                'cancelled_this_month' => $monthTravel->where('status', 'cancelled')->count(),
                'completion_rate' => $monthTravel->count() > 0
                    ? round(($monthTravel->where('status', 'completed')->count() / $monthTravel->count()) * 100, 1)
                    : 0,
            ],
            'pending_reviews' => [
                'registrations' => $pendingRegistrations,
                'requirements' => $pendingRequirements,
                'attendance' => $pendingAttendance,
                'journals' => $pendingJournals,
                'total' => $pendingRegistrations + $pendingRequirements + $pendingAttendance + $pendingJournals,
            ],
            'analytics' => [
                'average_progress' => round($averageProgress, 1),
                'internship_status' => [
                    ['label' => 'Active', 'value' => $activeStudents->count(), 'color' => '#059669'],
                    ['label' => 'Completed', 'value' => $students->where('internship_status', 'completed')->count(), 'color' => '#2563eb'],
                    ['label' => 'Pending', 'value' => $students->where('internship_status', 'pending')->count(), 'color' => '#d97706'],
                    ['label' => 'Dropped', 'value' => $students->where('internship_status', 'dropped')->count(), 'color' => '#dc2626'],
                ],
                'program_performance' => $programPerformance,
            ],
        ]);
    }

    private function renderedHours(Attendance $attendance): float
    {
        $regular = collect([
            [$attendance->am_time_in, $attendance->am_time_out],
            [$attendance->pm_time_in, $attendance->pm_time_out],
        ])->sum(fn (array $slot) => $slot[0] && $slot[1] ? $slot[0]->diffInSeconds($slot[1]) / 3600 : 0);

        if ($regular <= 0 && ! $attendance->am_time_in && ! $attendance->pm_time_in && $attendance->time_in && $attendance->time_out) {
            $regular = $attendance->time_in->diffInSeconds($attendance->time_out) / 3600;
        }

        return $regular + (float) $attendance->overtime_hours;
    }
}
