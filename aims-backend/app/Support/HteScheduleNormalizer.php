<?php

namespace App\Support;

use App\Models\HTE;
use App\Models\HteSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HteScheduleNormalizer
{
    public const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public static function syncLegacyInput(HTE $hte, array $input): void
    {
        if (! Schema::hasTable('hte_schedules') || ! self::hasScheduleInput($input)) {
            return;
        }

        $existing = $hte->schedules()->get()->keyBy('day_of_week');
        $workingDays = array_key_exists('work_days', $input)
            ? collect($input['work_days'] ?? [])->map(fn ($day) => strtolower(substr((string) $day, 0, 3)))->all()
            : $existing->where('is_working', true)->pluck('day_of_week')->all();
        $template = $existing->where('is_working', true)->first();

        $times = [
            'am_start' => $input['default_am_start'] ?? $template?->am_start,
            'am_end' => $input['default_am_end'] ?? $template?->am_end,
            'pm_start' => $input['default_pm_start'] ?? $template?->pm_start,
            'pm_end' => $input['default_pm_end'] ?? $template?->pm_end,
        ];

        if ($workingDays === [] || collect($times)->contains(fn ($value) => blank($value))) {
            throw new \LogicException('An HTE schedule requires working days and complete AM/PM hours.');
        }

        $days = collect(self::WEEKDAYS)->mapWithKeys(fn (string $day) => [$day => [
            'is_working' => in_array($day, $workingDays, true),
            ...$times,
        ]])->all();

        self::syncDays($hte, $days, (int) ($template?->grace_period_minutes ?? 10));
    }

    public static function syncDays(HTE $hte, array $days, int $gracePeriodMinutes): void
    {
        if (! Schema::hasTable('hte_schedules')) {
            return;
        }

        DB::transaction(function () use ($hte, $days, $gracePeriodMinutes): void {
            foreach (self::WEEKDAYS as $day) {
                $schedule = $days[$day];
                $isWorking = (bool) $schedule['is_working'];

                HteSchedule::updateOrCreate(
                    ['hte_id' => $hte->id, 'day_of_week' => $day],
                    [
                        'is_working' => $isWorking,
                        'grace_period_minutes' => $gracePeriodMinutes,
                        'am_start' => $isWorking ? $schedule['am_start'] : null,
                        'am_end' => $isWorking ? $schedule['am_end'] : null,
                        'pm_start' => $isWorking ? $schedule['pm_start'] : null,
                        'pm_end' => $isWorking ? $schedule['pm_end'] : null,
                    ],
                );
            }
        });

        $hte->unsetRelation('schedules');
    }

    private static function hasScheduleInput(array $input): bool
    {
        return collect([
            'default_am_start', 'default_am_end', 'default_pm_start', 'default_pm_end', 'work_days',
        ])->contains(fn (string $key) => array_key_exists($key, $input));
    }
}
