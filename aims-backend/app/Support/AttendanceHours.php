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

        if (! $attendance->date) {
            return $rawHours;
        }

        $date = $attendance->date->toDateString();
        $snapshots = $attendance->relationLoaded('scheduleSnapshots')
            ? $attendance->scheduleSnapshots->keyBy('period')
            : $attendance->scheduleSnapshots()->get()->keyBy('period');
        $am = $snapshots->get('am');
        $pm = $snapshots->get('pm');

        if (! $am || ! $pm) {
            $student ??= $attendance->student;
            if (! $student) {
                return $rawHours;
            }
            $schedule = EffectiveSchedule::forDate($student, $attendance->date);
            $amStart = $schedule['am_start'];
            $amEnd = $schedule['am_end'];
            $pmStart = $schedule['pm_start'];
            $pmEnd = $schedule['pm_end'];
            $isWorking = $schedule['is_working'];
        } else {
            $amStart = $am->scheduled_start;
            $amEnd = $am->scheduled_end;
            $pmStart = $pm->scheduled_start;
            $pmEnd = $pm->scheduled_end;
            $isWorking = $am->is_working || $pm->is_working;
        }

        if (! $isWorking || ! $amStart || ! $amEnd || ! $pmStart || ! $pmEnd) {
            return $rawHours;
        }

        return self::overlap(
            $attendance->am_time_in,
            $attendance->am_time_out,
            Carbon::parse($date.' '.$amStart),
            Carbon::parse($date.' '.$amEnd),
        ) + self::overlap(
            $attendance->pm_time_in,
            $attendance->pm_time_out,
            Carbon::parse($date.' '.$pmStart),
            Carbon::parse($date.' '.$pmEnd),
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
