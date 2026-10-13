<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\ProgramOjtSetting;
use App\Models\Student;
use Carbon\Carbon;

final class OjtCompletion
{
    public static function usesAutomaticEnd(Student $student): bool
    {
        return (bool) ($student->hte_id && $student->academic_period_id && $student->ojt_start_date
            && ProgramOjtSetting::publishedFor($student->program_id, $student->academic_period_id));
    }

    public static function forStudent(Student $student): ?array
    {
        if (! self::usesAutomaticEnd($student)) {
            return null;
        }

        $internship = $student->currentInternship;
        if (! $internship) {
            return null;
        }

        $required = (float) $internship->required_hours;
        $records = Attendance::query()->where('student_internship_id', $internship->id)
            ->whereIn('status', ['present', 'late'])
            ->whereDate('date', '>=', $internship->start_date?->toDateString() ?? $student->ojt_start_date->toDateString())
            ->with(['events', 'scheduleSnapshots'])->orderBy('date')->orderBy('id')->get()
            ->filter(fn (Attendance $record) => self::dayIsComplete($record));

        $completedHours = 0.0;
        $actual = null;
        foreach ($records as $record) {
            $completedHours += AttendanceHours::total($record, $student);
            if ($actual === null && $completedHours + 0.0001 >= $required) {
                $actual = $record->date->toDateString();
            }
        }

        $remaining = max($required - $completedHours, 0);
        $weekly = $actual === null ? EffectiveSchedule::week($student) : [];
        $scheduleError = $actual === null && self::hasInvalidSchedule($weekly);
        $unavailableToday = Attendance::query()->where('student_internship_id', $internship->id)
            ->whereDate('date', today()->toDateString())->whereIn('status', ['absent', 'holiday'])->exists();
        return [
            'completed_hours' => round($completedHours, 2),
            'remaining_hours' => round($remaining, 2),
            'estimated_end_date' => $actual ?? ($scheduleError ? null : self::estimate($student, $weekly, $remaining, $unavailableToday
                || $records->contains(fn (Attendance $record) => $record->date->isToday()))),
            'actual_completion_date' => $actual,
            'schedule_error' => $scheduleError,
        ];
    }

    private static function dayIsComplete(Attendance $record): bool
    {
        return match ($record->session_type) {
            'am_half' => $record->am_time_in && $record->am_time_out,
            'pm_half' => $record->pm_time_in && $record->pm_time_out,
            default => $record->am_time_in && $record->am_time_out && $record->pm_time_in && $record->pm_time_out,
        };
    }

    private static function estimate(Student $student, array $weekly, float $remaining, bool $hasCompletedToday): ?string
    {
        if ($remaining <= 0) {
            return null;
        }

        $holidays = Holiday::query()->with('htes:id')->where('program_id', $student->program_id)->get();
        $date = $student->ojt_start_date->copy()->startOfDay();
        if ($date->lt(today())) {
            $date = today();
        }

        for ($guard = 0; $guard < 1100; $guard++, $date->addDay()) {
            if ($hasCompletedToday && $date->isToday()) {
                continue;
            }
            $schedule = $weekly[strtolower($date->format('D'))] ?? null;
            if (! $schedule || ! $schedule['is_working'] || self::isHoliday($holidays, $student, $date)) {
                continue;
            }
            if ($date->isToday() && now()->format('H:i:s') >= ($schedule['pm_end'] ?? $schedule['am_end'] ?? '23:59:59')) {
                continue;
            }
            $daily = self::hours($schedule['am_start'], $schedule['am_end'])
                + self::hours($schedule['pm_start'], $schedule['pm_end']);
            if ($daily <= 0) {
                continue;
            }
            $remaining -= $daily;
            if ($remaining <= 0.0001) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private static function hasInvalidSchedule(array $weekly): bool
    {
        foreach ($weekly as $day) {
            if (! $day['is_working']) {
                continue;
            }

            if (! $day['am_start'] || ! $day['am_end'] || ! $day['pm_start'] || ! $day['pm_end']
                || $day['am_start'] >= $day['am_end']
                || $day['am_end'] > $day['pm_start']
                || $day['pm_start'] >= $day['pm_end']) {
                return true;
            }
        }

        return false;
    }

    private static function hours(?string $start, ?string $end): float
    {
        return $start && $end ? max(Carbon::parse($start)->diffInMinutes(Carbon::parse($end), false) / 60, 0) : 0;
    }

    private static function isHoliday($holidays, Student $student, Carbon $date): bool
    {
        return $holidays->contains(fn (Holiday $holiday) => (
            $holiday->is_recurring ? $holiday->date->format('m-d') === $date->format('m-d') : $holiday->date->isSameDay($date)
        ) && ($holiday->holiday_type !== 'local_holiday' || $holiday->htes->contains('id', $student->hte_id)));
    }
}
