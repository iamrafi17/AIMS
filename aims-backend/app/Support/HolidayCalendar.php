<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HolidayCalendar
{
    public static function forStudentOn(Student $student, Carbon $date): ?Holiday
    {
        return self::programHolidays((int) $student->program_id)
            ->first(fn (Holiday $holiday) => self::occursOn($holiday, $date)
                && self::appliesToStudent($holiday, $student));
    }

    public static function monthForStudent(Student $student, Carbon $month): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return self::programHolidays((int) $student->program_id)
            ->filter(fn (Holiday $holiday) => self::appliesToStudent($holiday, $student))
            ->map(function (Holiday $holiday) use ($start, $student) {
                $occurrence = $holiday->is_recurring
                    ? self::occurrenceInYear($holiday, $start->year)
                    : $holiday->date->copy()->startOfDay();

                return $occurrence ? self::payload($holiday, $occurrence, $student) : null;
            })
            ->filter(fn (?array $item) => $item
                && Carbon::parse($item['date'])->betweenIncluded($start, $end))
            ->sortBy('date')
            ->values();
    }

    public static function forecastForStudent(Student $student, int $limit = 5): array
    {
        $today = now()->startOfDay();
        $items = self::programHolidays((int) $student->program_id)
            ->filter(fn (Holiday $holiday) => self::appliesToStudent($holiday, $student))
            ->map(function (Holiday $holiday) use ($student, $today) {
                $occurrence = self::nextOccurrence($holiday, $today);

                if ($occurrence && $student->ojt_end_date && $occurrence->gt($student->ojt_end_date->copy()->endOfDay())) {
                    return null;
                }

                return $occurrence ? self::payload($holiday, $occurrence, $student) : null;
            })
            ->filter()
            ->sortBy('date')
            ->take($limit)
            ->values();

        return [
            'next' => $items->first(),
            'upcoming' => $items,
            'working_days_affected' => $items->where('affects_workday', true)->count(),
        ];
    }

    public static function projectedCompletion(Student $student, int $workingDaysLeft): ?string
    {
        if ($workingDaysLeft <= 0) {
            return now()->toDateString();
        }

        $date = now()->startOfDay();
        $remaining = $workingDaysLeft;
        $holidays = self::programHolidays((int) $student->program_id)
            ->filter(fn (Holiday $holiday) => self::appliesToStudent($holiday, $student));

        for ($guard = 0; $guard < 1100; $guard++) {
            $isHoliday = $holidays->contains(fn (Holiday $holiday) => self::occursOn($holiday, $date));
            $isWorkday = EffectiveSchedule::forDate($student, $date)['is_working'];
            if ($isWorkday && ! $isHoliday) {
                $remaining--;
                if ($remaining === 0) {
                    return $date->toDateString();
                }
            }
            $date->addDay();
        }

        return null;
    }

    public static function forecastForProgram(int $programId, int $limit = 8): array
    {
        $today = now()->startOfDay();
        $students = Student::query()
            ->forProgram($programId)
            ->deployed()
            ->forInternshipStatus('active')
            ->get();

        $items = self::programHolidays($programId)
            ->map(function (Holiday $holiday) use ($students, $today) {
                $occurrence = self::nextOccurrence($holiday, $today);
                if (! $occurrence) {
                    return null;
                }

                $affected = $students->filter(fn (Student $student) => self::appliesToStudent($holiday, $student));
                $payload = self::payload($holiday, $occurrence);
                $payload['affected_students'] = $affected->count();
                $payload['affected_htes'] = $holiday->holiday_type === 'local_holiday'
                    ? $holiday->htes->map(fn ($hte) => ['id' => $hte->id, 'name' => $hte->name])->values()
                    : collect();

                return $payload;
            })
            ->filter()
            ->sortBy('date')
            ->take($limit)
            ->values();

        return [
            'next' => $items->first(),
            'upcoming' => $items,
            'upcoming_count' => $items->count(),
        ];
    }

    public static function payload(Holiday $holiday, Carbon $occurrence, ?Student $student = null): array
    {
        $today = now()->startOfDay();

        return [
            'id' => $holiday->id,
            'name' => $holiday->name,
            'date' => $occurrence->toDateString(),
            'holiday_type' => $holiday->holiday_type,
            'type_label' => self::typeLabel($holiday->holiday_type),
            'description' => $holiday->description,
            'official_reference' => $holiday->official_reference,
            'source_url' => $holiday->source_url,
            'affected_area' => $holiday->affected_area,
            'is_recurring' => (bool) $holiday->is_recurring,
            'days_until' => max((int) $today->diffInDays($occurrence, false), 0),
            'is_today' => $occurrence->isSameDay($today),
            'affects_workday' => $student ? self::isStudentWorkday($student, $occurrence) : null,
        ];
    }

    private static function programHolidays(int $programId): Collection
    {
        return Holiday::query()
            ->with('htes:id,name')
            ->where('program_id', $programId)
            ->get();
    }

    private static function occursOn(Holiday $holiday, Carbon $date): bool
    {
        return $holiday->is_recurring
            ? $holiday->date->format('m-d') === $date->format('m-d')
            : $holiday->date->isSameDay($date);
    }

    private static function appliesToStudent(Holiday $holiday, Student $student): bool
    {
        if ($holiday->holiday_type !== 'local_holiday') {
            return true;
        }

        return $student->hte_id !== null && $holiday->htes->contains('id', $student->hte_id);
    }

    private static function nextOccurrence(Holiday $holiday, Carbon $today): ?Carbon
    {
        if (! $holiday->is_recurring) {
            $date = $holiday->date->copy()->startOfDay();

            return $date->gte($today) ? $date : null;
        }

        for ($year = $today->year; $year <= $today->year + 8; $year++) {
            $date = self::occurrenceInYear($holiday, $year);
            if ($date && $date->gte($today)) {
                return $date;
            }
        }

        return null;
    }

    private static function occurrenceInYear(Holiday $holiday, int $year): ?Carbon
    {
        $month = (int) $holiday->date->format('m');
        $day = (int) $holiday->date->format('d');

        return checkdate($month, $day, $year)
            ? Carbon::create($year, $month, $day, 0, 0, 0, config('app.timezone'))
            : null;
    }

    public static function isStudentWorkday(Student $student, Carbon $date): bool
    {
        return EffectiveSchedule::forDate($student, $date)['is_working'];
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'local_holiday' => 'Local Holiday',
            'special_non_working' => 'Special Non-working Day',
            default => 'National Holiday',
        };
    }
}
