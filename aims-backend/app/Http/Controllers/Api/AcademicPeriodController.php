<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicPeriodController extends Controller
{
    public function index()
    {
        $periods = AcademicPeriod::withCount([
            'enrollments as students_count' => fn ($query) => $query->whereNotNull('student_record_id'),
            'enrollments',
            'sections',
            'reportTemplates',
            'generatedReports',
        ])
            ->orderByDesc('start_date')->orderByDesc('id')->get();

        return response()->json(['periods' => $periods]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $period = $this->mutate(function () use ($data) {
            $this->deactivatePrevious($data['status']);
            $period = AcademicPeriod::create($data);

            return $period;
        });

        return response()->json(['message' => 'Academic period created successfully.', 'period' => $period], 201);
    }

    public function update(Request $request, AcademicPeriod $academicPeriod)
    {
        $data = $this->validated($request, $academicPeriod);
        $period = $this->mutate(function () use ($data, $academicPeriod) {
            $period = AcademicPeriod::lockForUpdate()->findOrFail($academicPeriod->id);
            $this->ensureEditableStatus($period, $data['status']);
            $this->deactivatePrevious($data['status'], $period->id);
            $period->update($data);

            return $period;
        });

        return response()->json(['message' => 'Academic period updated successfully.', 'period' => $period]);
    }

    public function activate(AcademicPeriod $academicPeriod)
    {
        $period = $this->mutate(function () use ($academicPeriod) {
            $period = AcademicPeriod::lockForUpdate()->findOrFail($academicPeriod->id);
            $this->ensureEditableStatus($period, 'active');
            $this->deactivatePrevious('active', $period->id);
            $period->update(['status' => 'active']);

            return $period;
        });

        return response()->json(['message' => 'Academic period set as active. The previous active period is now Draft.', 'period' => $period]);
    }

    public function complete(AcademicPeriod $academicPeriod)
    {
        $period = $this->mutate(function () use ($academicPeriod) {
            $period = AcademicPeriod::lockForUpdate()->findOrFail($academicPeriod->id);
            $period->update(['status' => 'completed', 'completed_at' => $period->completed_at ?? now()]);

            return $period;
        });

        return response()->json(['message' => 'Academic period completed and archived. Linked records were preserved.', 'period' => $period]);
    }

    public function destroy(AcademicPeriod $academicPeriod)
    {
        $this->mutate(function () use ($academicPeriod) {
            $period = AcademicPeriod::lockForUpdate()->findOrFail($academicPeriod->id);
            abort_if($period->enrollments()->exists() || $period->sections()->exists()
                || $period->reportTemplates()->exists(),
                422, 'This academic period has linked records. Complete / archive it instead.');
            $period->delete();
        });

        return response()->json(['message' => 'Academic period deleted successfully.']);
    }

    private function validated(Request $request, ?AcademicPeriod $period = null): array
    {
        $year = $request->input('academic_year');
        $semester = $request->input('semester');
        $request->merge([
            'academic_year' => is_string($year) ? AcademicPeriod::normalizeYear($year) : $year,
            'semester' => is_string($semester) ? AcademicPeriod::normalizeSemester($semester) : $semester,
        ]);
        $data = $request->validate([
            'academic_year' => ['bail', 'required', 'string', 'regex:/^\d{4}-\d{4}$/', Rule::unique('academic_periods', 'academic_year')
                ->where('semester', is_string($semester) ? AcademicPeriod::normalizeSemester($semester) : '')->ignore($period?->id)],
            'semester' => ['required', 'string', Rule::in(AcademicPeriod::SEMESTERS)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in($period ? ['draft', 'active', 'completed'] : ['draft', 'active'])],
        ]);
        [$start, $end] = array_map('intval', explode('-', $data['academic_year']));
        if ($end !== $start + 1) {
            throw ValidationException::withMessages(['academic_year' => 'Use consecutive years, for example 2026-2027.']);
        }
        if ((int) substr($data['start_date'], 0, 4) < $start || (int) substr($data['end_date'], 0, 4) > $end) {
            throw ValidationException::withMessages(['start_date' => 'The start and end dates must fall within the selected academic year.']);
        }
        $data['completed_at'] = $data['status'] === 'completed' ? ($period?->completed_at ?? now()) : null;

        return $data;
    }

    private function mutate(callable $callback): mixed
    {
        try {
            return DB::transaction(function () use ($callback) {
                DB::table('academic_period_locks')->where('id', 1)->lockForUpdate()->first();

                return $callback();
            }, 3);
        } catch (QueryException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages(['academic_year' => 'The period conflicts with an existing period or has linked records. Reload the list and try again.']);
            }
            throw $error;
        }
    }

    private function deactivatePrevious(string $status, ?int $except = null): void
    {
        if ($status === 'active') {
            AcademicPeriod::where('status', 'active')->when($except, fn ($query) => $query->where('id', '!=', $except))
                ->update(['status' => 'draft']);
        }
    }

    private function ensureEditableStatus(AcademicPeriod $period, string $status): void
    {
        abort_if($period->status === 'completed' && $status !== 'completed', 422, 'Completed periods stay archived. Create a new period instead.');
    }
}
