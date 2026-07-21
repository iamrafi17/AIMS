<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\InternshipRequirement;
use App\Models\Student;
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

        $attendanceRecords = Attendance::where('student_id', $student->id)
            ->orderBy('date')
            ->get([
                'date',
                'status',
                'time_in',
                'time_out',
                'am_time_in',
                'am_time_out',
                'pm_time_in',
                'pm_time_out',
                'overtime_hours',
                'am_activity',
                'pm_activity',
            ]);

        $monthlyAttendance = $attendanceRecords->filter(fn (Attendance $attendance) => $attendance->date->month === now()->month
            && $attendance->date->year === now()->year
        );

        $renderedHours = $attendanceRecords->sum(fn (Attendance $attendance) => $this->attendanceHours($attendance)
        );

        $attendanceDays = $attendanceRecords
            ->whereIn('status', ['present', 'late'])
            ->count();

        $activityEntries = $attendanceRecords->sum(fn (Attendance $attendance) => (blank($attendance->am_activity) ? 0 : 1)
            + (blank($attendance->pm_activity) ? 0 : 1)
        );

        $weekStart = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weeklyHours = collect(range(0, 6))->map(function (int $offset) use ($attendanceRecords, $weekStart) {
            $date = $weekStart->copy()->addDays($offset);
            $hours = $attendanceRecords
                ->filter(fn (Attendance $attendance) => $attendance->date->isSameDay($date))
                ->sum(fn (Attendance $attendance) => $this->attendanceHours($attendance));

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

        // Get pending requirements
        $pendingRequirements = InternshipRequirement::where('student_id', $student->id)
            ->where('status', 'pending')
            ->count();

        // Get recent announcements
        $announcements = Announcement::where('is_published', true)
            ->whereIn('target_audience', ['all', 'students'])
            ->latest()
            ->limit(5)
            ->get();

        // Check if student has clocked in today
        $todayAttendance = Attendance::where('student_id', $student->id)
            ->whereDate('date', $today)
            ->first();

        return response()->json([
            'student' => [
                'full_name' => $student->full_name,
                'student_id' => $student->student_id,
                'college' => $student->college->name,
                'program' => $student->program->name,
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
                'days_left' => $schedule['days_left'],
                'semester_elapsed_percent' => $schedule['elapsed_percent'],
                'start_date' => $student->ojt_start_date?->toDateString(),
                'end_date' => $student->ojt_end_date?->toDateString(),
                'period_state' => $schedule['state'],
                'period_message' => $schedule['message'],
                'this_week_hours' => round($weeklyHours->sum('hours'), 2),
                'weekly_hours' => $weeklyHours,
            ],
            'rendered_hours' => round($renderedHours, 2),
            'pending_requirements' => $pendingRequirements,
            'announcements' => $announcements,
            'today_attendance' => $todayAttendance ? [
                'time_in' => $todayAttendance->time_in,
                'time_out' => $todayAttendance->time_out,
                'status' => $todayAttendance->status,
                'work_mode' => $todayAttendance->work_mode,
                'session_type' => $todayAttendance->session_type,
            ] : null,
        ]);
    }

    private function attendanceHours(Attendance $attendance): float
    {
        $slotHours = collect([
            [$attendance->am_time_in, $attendance->am_time_out],
            [$attendance->pm_time_in, $attendance->pm_time_out],
        ])->sum(fn (array $slot) => $slot[0] && $slot[1]
            ? $slot[0]->diffInSeconds($slot[1]) / 3600
            : 0);

        if ($slotHours > 0 || $attendance->am_time_in || $attendance->pm_time_in) {
            return $slotHours + (float) $attendance->overtime_hours;
        }

        $regularHours = $attendance->time_in && $attendance->time_out
            ? $attendance->time_in->diffInSeconds($attendance->time_out) / 3600
            : 0;

        return $regularHours + (float) $attendance->overtime_hours;
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
