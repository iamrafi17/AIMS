<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Student;
use Carbon\Carbon;

class AttendanceHours
{
    public static function regular(Attendance $attendance, ?Student $student = null): float
    {
        $rawSlots = collect([
            [$attendance->am_time_in, $attendance->am_time_out],
            [$attendance->pm_time_in, $attendance->pm_time_out],
        ]);
        $rawHours = $rawSlots->sum(fn (array $slot) => self::duration($slot[0], $slot[1]));

        if ($rawHours <= 0 && ! $attendance->am_time_in && ! $attendance->pm_time_in) {
            return self::duration($attendance->time_in, $attendance->time_out);
        }

        $student ??= $attendance->student;
        if (! $student || ! $attendance->date || ! $student->official_am_start || ! $student->official_am_end
            || ! $student->official_pm_start || ! $student->official_pm_end) {
            return $rawHours;
        }

        $date = $attendance->date->toDateString();

        return self::overlap(
            $attendance->am_time_in,
            $attendance->am_time_out,
            Carbon::parse($date.' '.$student->official_am_start),
            Carbon::parse($date.' '.$student->official_am_end),
        ) + self::overlap(
            $attendance->pm_time_in,
            $attendance->pm_time_out,
            Carbon::parse($date.' '.$student->official_pm_start),
            Carbon::parse($date.' '.$student->official_pm_end),
        );
    }

    public static function total(Attendance $attendance, ?Student $student = null): float
    {
        return self::regular($attendance, $student) + (float) $attendance->overtime_hours;
    }

    private static function duration(?Carbon $start, ?Carbon $end): float
    {
        return $start && $end && $end->gt($start) ? $start->diffInSeconds($end) / 3600 : 0;
    }

    private static function overlap(?Carbon $actualStart, ?Carbon $actualEnd, Carbon $scheduleStart, Carbon $scheduleEnd): float
    {
        if (! $actualStart || ! $actualEnd || ! $actualEnd->gt($actualStart)) {
            return 0;
        }

        $start = $actualStart->gt($scheduleStart) ? $actualStart : $scheduleStart;
        $end = $actualEnd->lt($scheduleEnd) ? $actualEnd : $scheduleEnd;

        return $end->gt($start) ? $start->diffInSeconds($end) / 3600 : 0;
    }
}
