<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\Program;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorHolidayImportController extends Controller
{
    public function preview(Request $request)
    {
        $year = $this->year($request);
        ProgramAccess::programId($request->user());

        return response()->json([
            'year' => $year,
            'source' => $this->sourceUrl($year),
            'holidays' => $this->withExistingStatus($this->nationalHolidays($year)),
        ]);
    }

    public function import(Request $request)
    {
        $year = $this->year($request);
        $validated = $request->validate([
            'dates' => ['required', 'array', 'min:1'],
            'dates.*' => ['required', 'date_format:Y-m-d', 'distinct'],
            'official_reference' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'holiday_types' => ['sometimes', 'array'],
            'holiday_types.*' => [Rule::in(['national_holiday', 'special_non_working'])],
        ]);
        ProgramAccess::programId($request->user());
        $holidays = $this->nationalHolidays($year)->keyBy('date');
        $selected = collect($validated['dates']);
        if ($selected->contains(fn (string $date) => ! $holidays->has($date))) {
            throw ValidationException::withMessages(['dates' => 'Select only national holidays returned by the API for this year.']);
        }

        $created = DB::transaction(function () use ($selected, $holidays, $validated, $year) {
            $count = 0;
            $programs = Program::query()->get(['id', 'college_id']);
            foreach ($selected as $date) {
                $dateCreated = false;
                foreach ($programs as $program) {
                    if ($this->alreadySaved($program->id, $date)) {
                        continue;
                    }

                    Holiday::create([
                        'college_id' => $program->college_id,
                        'program_id' => $program->id,
                        'name' => $holidays[$date]['name'],
                        'date' => $date,
                        'holiday_type' => $validated['holiday_types'][$date] ?? 'national_holiday',
                        'official_reference' => $validated['official_reference'],
                        'source_url' => $validated['source_url'] ?? $this->sourceUrl($year),
                        'description' => 'Imported from Nager.Holidays after coordinator review.',
                        'is_recurring' => false,
                    ]);
                    $dateCreated = true;
                }
                $count += (int) $dateCreated;
            }

            return $count;
        });

        return response()->json([
            'message' => "{$created} nationwide holiday(s) imported.",
            'imported' => $created,
            'skipped' => $selected->count() - $created,
        ]);
    }

    private function year(Request $request): int
    {
        $validated = $request->validate(['year' => ['required', 'integer', 'between:2000,2100']]);

        return (int) $validated['year'];
    }

    private function sourceUrl(int $year): string
    {
        return "https://nagerholidays.com/api/v4/Holidays/PH/{$year}";
    }

    /** @return Collection<int, array{date: string, name: string}> */
    private function nationalHolidays(int $year): Collection
    {
        try {
            $response = Http::acceptJson()->timeout(12)->get($this->sourceUrl($year));
        } catch (\Throwable $exception) {
            abort(503, 'Holiday API is unavailable. Try again later.');
        }

        if (! $response->successful() || ! is_array($response->json()) || ! array_is_list($response->json())) {
            abort(503, 'Holiday API returned an invalid response.');
        }

        return collect($response->json())
            ->filter(fn ($item) => is_array($item)
                && ($item['countryCode'] ?? null) === 'PH'
                && ($item['nationalHoliday'] ?? false) === true
                && in_array('Public', $item['holidayTypes'] ?? [], true)
                && is_string($item['date'] ?? null)
                && preg_match('/^'.$year.'-\d{2}-\d{2}$/', $item['date'])
                && checkdate((int) substr($item['date'], 5, 2), (int) substr($item['date'], 8, 2), $year)
                && is_string($item['name'] ?? null)
                && trim($item['name']) !== '')
            ->map(fn ($item) => ['date' => $item['date'], 'name' => trim($item['name'])])
            ->unique('date')
            ->sortBy('date')
            ->values();
    }

    /**
     * @param  Collection<int, array{date: string, name: string}>  $holidays
     * @return Collection<int, array{date: string, name: string, already_saved: bool}>
     */
    private function withExistingStatus(Collection $holidays): Collection
    {
        $programIds = Program::query()->pluck('id');

        return $holidays->map(fn ($item) => [
            ...$item,
            'already_saved' => $programIds->every(fn ($programId) => $this->alreadySaved($programId, $item['date'])),
        ])->values();
    }

    private function alreadySaved(int $programId, string $date): bool
    {
        return Holiday::where('program_id', $programId)
            ->where('holiday_type', '!=', 'local_holiday')
            ->get(['date', 'is_recurring'])
            ->contains(fn (Holiday $holiday) => $holiday->is_recurring
                ? $holiday->date->format('m-d') === substr($date, 5)
                : $holiday->date->toDateString() === $date);
    }
}
