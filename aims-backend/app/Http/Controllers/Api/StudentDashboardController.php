<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\InternshipRequirement;
use App\Models\Student;
use App\Support\AttendanceHours;
use App\Support\EffectiveSchedule;
use App\Support\HolidayCalendar;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        $today = now()->toDateString();

        $attendanceRecords = Attendance::forStudent($student->id)
            ->orderBy('date')
            ->with(['events', 'scheduleSnapshots', 'journals'])
            ->get();

        $monthlyAttendance = $attendanceRecords->filter(fn (Attendance $attendance) => $attendance->date->month === now()->month
            && $attendance->date->year === now()->year
        );

        $renderedHours = $attendanceRecords->sum(fn (Attendance $attendance) => $this->attendanceHours($attendance, $student)
        );

        $attendanceDays = $attendanceRecords
            ->whereIn('status', ['present', 'late'])
            ->count();

        $activityEntries = $attendanceRecords->sum(fn (Attendance $attendance) => (blank($attendance->am_activity) ? 0 : 1)
            + (blank($attendance->pm_activity) ? 0 : 1)
        );

        $weekStart = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weeklyHours = collect(range(0, 6))->map(function (int $offset) use ($attendanceRecords, $student, $weekStart) {
            $date = $weekStart->copy()->addDays($offset);
            $hours = $attendanceRecords
                ->filter(fn (Attendance $attendance) => $attendance->date->isSameDay($date))
                ->sum(fn (Attendance $attendance) => $this->attendanceHours($attendance, $student));

            return [
                'day' => $date->format('D'),
                'date' => $date->toDateString(),
                'hours' => round($hours, 2),
            ];
        })->values();

        $requiredHours = max((float) $student->required_ojt_hours, 0);
        $hoursLeft = max($requiredHours - $renderedHours, 0);
        $progressPercent = $requiredHours > 0
            ? min(($renderedHours / $requiredHours) * 100, 100)
            : 0;

        $schedule = $this->scheduleProgress($student);
        $dailyScheduledHours = $this->dailyScheduledHours($student);
        $workingDaysLeft = $hoursLeft > 0 ? (int) ceil($hoursLeft / $dailyScheduledHours) : 0;
        $holidayForecast = HolidayCalendar::forecastForStudent($student);

        $requirements = InternshipRequirement::where('student_id', $student->id)
            ->activeDefinitionOrLegacy()
            ->get(['id', 'status']);
        $requirementTotal = $requirements->count();
        $approvedRequirements = $requirements->where('status', 'approved')->count();
        $requirementPercent = $requirementTotal > 0
            ? min(($approvedRequirements / $requirementTotal) * 100, 100)
            : 0;

        // Get recent announcements
        $announcements = Announcement::where('is_published', true)
            ->whereIn('target_audience', ['all', 'students'])
            ->latest()
            ->limit(5)
            ->get();

        // Check if student has clocked in today
        $todayAttendance = Attendance::forStudent($student->id)
            ->whereDate('date', $today)
            ->first();
        $tasks = $student->tasks()
            ->whereIn('status', ['assigned', 'in_progress'])
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->limit(6)
            ->get(['id', 'title', 'description', 'due_date', 'priority', 'status']);

        return response()->json([
            'student' => [
                'full_name' => $student->full_name,
                'student_id' => $student->student_id,
                'college' => $student->college?->name ?? 'Not assigned',
                'program' => $student->program?->name ?? 'Not assigned',
                'year_level' => $student->year_level,
                'section' => $student->section,
                'hte' => $student->hte?->name,
                'internship_status' => $student->internship_status,
            ],
            'attendance_summary' => [
                'present' => $monthlyAttendance->where('status', 'present')->count(),
                'absent' => $monthlyAttendance->where('status', 'absent')->count(),
                'late' => $monthlyAttendance->where('status', 'late')->count(),
            ],
            'dashboard_status' => [
                'consent' => $student->consent_status,
                'schedule' => $student->schedule_status,
                'attendance_days' => $attendanceDays,
            ],
            'ojt_progress' => [
                'rendered_hours' => round($renderedHours, 2),
                'required_hours' => round($requiredHours, 2),
                'hours_left' => round($hoursLeft, 2),
                'percent_complete' => round($progressPercent, 1),
                'entries' => $activityEntries,
                'days_left' => $workingDaysLeft,
                'working_days_left' => $workingDaysLeft,
                'calendar_days_left' => $schedule['days_left'],
                'daily_scheduled_hours' => round($dailyScheduledHours, 2),
                'projected_completion_date' => HolidayCalendar::projectedCompletion($student, $workingDaysLeft),
                'semester_elapsed_percent' => $schedule['elapsed_percent'],
                'start_date' => $student->ojt_start_date?->toDateString(),
                'end_date' => $student->ojt_end_date?->toDateString(),
                'period_state' => $schedule['state'],
                'period_message' => $schedule['message'],
                'this_week_hours' => round($weeklyHours->sum('hours'), 2),
                'weekly_hours' => $weeklyHours,
            ],
            'rendered_hours' => round($renderedHours, 2),
            'requirements_progress' => [
                'approved' => $approvedRequirements,
                'total' => $requirementTotal,
                'pending' => $requirements->where('status', 'pending')->count(),
                'percent_complete' => round($requirementPercent, 1),
            ],
            'pending_requirements' => $requirements->where('status', 'pending')->count(),
            'holiday_forecast' => $holidayForecast,
            'announcements' => $announcements,
            'tasks' => $tasks,
            'today_attendance' => $todayAttendance ? [
                'time_in' => $todayAttendance->time_in,
                'time_out' => $todayAttendance->time_out,
                'status' => $todayAttendance->status,
                'work_mode' => $todayAttendance->work_mode,
                'session_type' => $todayAttendance->session_type,
            ] : null,
        ]);
    }

    private function attendanceHours(Attendance $attendance, Student $student): float
    {
        return AttendanceHours::total($attendance, $student);
    }

    private function dailyScheduledHours(Student $student): float
    {
        $dailyHours = collect(EffectiveSchedule::week($student))
            ->where('is_working', true)
            ->map(fn (array $schedule) => $this->scheduledMinutes([
                [$schedule['am_start'], $schedule['am_end']],
                [$schedule['pm_start'], $schedule['pm_end']],
            ]) / 60)
            ->filter(fn (float $hours) => $hours > 0);

        return $dailyHours->isNotEmpty() ? (float) $dailyHours->average() : 8.0;
    }

    private function scheduledMinutes(array $periods): int
    {
        $minutes = 0;
        foreach ($periods as [$startValue, $endValue]) {
            if (! $startValue || ! $endValue) {
                continue;
            }

            $start = Carbon::parse($startValue);
            $end = Carbon::parse($endValue);
            if ($end->gt($start)) {
                $minutes += $start->diffInMinutes($end);
            }
        }

        return $minutes;
    }

    private function scheduleProgress(Student $student): array
    {
        if (! $student->ojt_start_date || ! $student->ojt_end_date) {
            return [
                'days_left' => null,
                'elapsed_percent' => 0,
                'state' => 'not_configured',
                'message' => 'OJT schedule dates are not configured yet.',
            ];
        }

        $today = now()->startOfDay();
        $start = $student->ojt_start_date->copy()->startOfDay();
        $end = $student->ojt_end_date->copy()->startOfDay();

        if ($end->lt($start)) {
            return [
                'days_left' => null,
                'elapsed_percent' => 0,
                'state' => 'invalid',
                'message' => 'The configured OJT schedule dates need correction.',
            ];
        }

        $totalDays = max($start->diffInDays($end), 1);
        $elapsedPercent = $today->lt($start)
            ? 0
            : ($today->gt($end) ? 100 : min(($start->diffInDays($today) / $totalDays) * 100, 100));

        if ($today->lt($start)) {
            $days = (int) $today->diffInDays($start);

            return [
                'days_left' => (int) $today->diffInDays($end),
                'elapsed_percent' => round($elapsedPercent, 1),
                'state' => 'upcoming',
                'message' => 'OJT period starts in '.$days.' '.str('day')->plural($days).'.',
            ];
        }

        if ($today->gt($end)) {
            $days = (int) $end->diffInDays($today);

            return [
                'days_left' => 0,
                'elapsed_percent' => 100,
                'state' => 'ended',
                'message' => 'OJT period ended '.$days.' '.str('day')->plural($days).' ago.',
            ];
        }

        $days = (int) $today->diffInDays($end);

        return [
            'days_left' => $days,
            'elapsed_percent' => round($elapsedPercent, 1),
            'state' => 'active',
            'message' => $days === 0
                ? 'Today is the final day of the OJT period.'
                : $days.' '.str('day')->plural($days).' left in the OJT period.',
        ];
    }
}
