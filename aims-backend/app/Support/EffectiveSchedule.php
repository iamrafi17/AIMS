<?php

namespace App\Support;

use App\Models\HteBatchSchedule;
use App\Models\HteSchedule;
use App\Models\Student;
use App\Models\StudentScheduleOverride;
use Carbon\Carbon;

class EffectiveSchedule
{
    private const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public static function forDate(Student $student, Carbon $date): array
    {
        $day = strtolower($date->format('D'));
        $batch = self::batchForDay($student, $day);
        $override = self::overrideForDay($student, $day);
        $hteSchedule = self::hteForDay($student, $day);

        if ($override) {
            return self::payload(
                $day,
                (bool) $override->is_working,
                $override->am_start,
                $override->am_end,
                $override->pm_start,
                $override->pm_end,
                (int) ($batch?->grace_period_minutes ?? $hteSchedule?->grace_period_minutes ?? 0),
                'student_override',
                true,
            );
        }

        if ($batch) {
            return self::payload(
                $day,
                (bool) $batch->is_working,
                $batch->am_start,
                $batch->am_end,
                $batch->pm_start,
                $batch->pm_end,
                (int) $batch->grace_period_minutes,
                'batch_schedule',
                true,
            );
        }

        if ($hteSchedule) {
            return self::payload(
                $day,
                (bool) $hteSchedule->is_working,
                $hteSchedule->am_start,
                $hteSchedule->am_end,
                $hteSchedule->pm_start,
                $hteSchedule->pm_end,
                (int) $hteSchedule->grace_period_minutes,
                'hte_schedule',
                true,
            );
        }

        return self::payload(
            $day,
            false,
            null,
            null,
            null,
            null,
            0,
            'unconfigured',
            false,
        );
    }

    public static function workingDays(Student $student): array
    {
        $monday = now()->startOfWeek(Carbon::MONDAY);

        return collect(self::WEEKDAYS)
            ->filter(fn (string $day, int $index) => self::forDate($student, $monday->copy()->addDays($index))['is_working'])
            ->values()
            ->all();
    }

    public static function week(Student $student): array
    {
        $monday = now()->startOfWeek(Carbon::MONDAY);

        return collect(self::WEEKDAYS)->mapWithKeys(
            fn (string $day, int $index) => [$day => self::forDate($student, $monday->copy()->addDays($index))],
        )->all();
    }

    private static function overrideForDay(Student $student, string $day): ?StudentScheduleOverride
    {
        $internshipId = $student->currentInternship?->id
            ?? $student->currentInternship()->value('id');

        return $internshipId
            ? StudentScheduleOverride::where('student_internship_id', $internshipId)->where('day_of_week', $day)->first()
            : null;
    }

    private static function batchForDay(Student $student, string $day): ?HteBatchSchedule
    {
        if (! $student->hte_id || ! $student->academic_section_id) {
            return null;
        }

        return HteBatchSchedule::query()
            ->where('hte_id', $student->hte_id)
            ->where('academic_section_id', $student->academic_section_id)
            ->where('day_of_week', $day)
            ->first();
    }

    private static function hteForDay(Student $student, string $day): ?HteSchedule
    {
        if (! $student->hte_id) {
            return null;
        }

        return HteSchedule::query()
            ->where('hte_id', $student->hte_id)
            ->where('day_of_week', $day)
            ->first();
    }

    private static function payload(
        string $day,
        bool $isWorking,
        ?string $amStart,
        ?string $amEnd,
        ?string $pmStart,
        ?string $pmEnd,
        int $gracePeriodMinutes,
        string $source,
        bool $isConfigured,
    ): array {
        return [
            'day_of_week' => $day,
            'is_configured' => $isConfigured,
            'is_working' => $isWorking,
            'am_start' => self::shortTime($amStart),
            'am_end' => self::shortTime($amEnd),
            'pm_start' => self::shortTime($pmStart),
            'pm_end' => self::shortTime($pmEnd),
            'grace_period_minutes' => $gracePeriodMinutes,
            'source' => $source,
        ];
    }

    private static function shortTime(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }
}
