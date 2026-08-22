<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Support\AttendanceHours;
use App\Support\ProgramAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:present,late,absent,holiday'],
            'verification' => ['nullable', 'in:verified,pending'],
            'journal' => ['nullable', 'in:submitted,missing,pending,reviewed'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        // Keep the summary query lean.  Loading relations for every matching
        // attendance record becomes expensive as a program accumulates history.
        $query = $this->applyFilters(
            Attendance::query()
                ->whereHas('student', fn (Builder $student) => $student->where('program_id', ProgramAccess::programId($request->user()))),
            $request
        );

        $summary = $this->summarize((clone $query));
        $records = (clone $query)
            ->with(['student.user', 'student.program', 'student.hte', 'verifier', 'journalReviewer'])
            ->latest('date')
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();
        $records->getCollection()->transform(fn (Attendance $attendance) => $this->recordPayload($attendance));

        return response()->json([
            ...$records->toArray(),
            'summary' => [
                'total' => $summary['total'],
                'present' => $summary['statuses']['present'],
                'late' => $summary['statuses']['late'],
                'absent' => $summary['statuses']['absent'],
                'holiday' => $summary['statuses']['holiday'],
                'verified' => $summary['verified'],
                'pending_verification' => $summary['pending_verification'],
                'attendance_rate' => $summary['attendance_rate'],
                'total_hours' => $summary['total_hours'],
                'journals_submitted' => $summary['journals_submitted'],
                'journals_pending' => $summary['journals_pending'],
            ],
            'analytics' => $summary['analytics'],
        ]);
    }

    public function show(Request $request, Attendance $attendance)
    {
        ProgramAccess::authorizeAttendance($request->user(), $attendance);

        return response()->json($this->recordPayload(
            $attendance->load(['student.user', 'student.program', 'student.hte', 'verifier', 'journalReviewer'])
        ));
    }

    public function update(Request $request, Attendance $attendance)
    {
        ProgramAccess::authorizeAttendance($request->user(), $attendance);
        $validated = $request->validate([
            'status' => ['required', 'in:present,late,absent,holiday'],
            'work_mode' => ['required', 'in:wfo,wfh,field'],
            'session_type' => ['required', 'in:full_day,am_half,pm_half'],
            'am_time_in' => ['nullable', 'date'],
            'am_time_out' => ['nullable', 'date', 'after_or_equal:am_time_in'],
            'pm_time_in' => ['nullable', 'date'],
            'pm_time_out' => ['nullable', 'date', 'after_or_equal:pm_time_in'],
            'ot_start' => ['nullable', 'date'],
            'ot_end' => ['nullable', 'date', 'after_or_equal:ot_start'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'am_activity' => ['nullable', 'string', 'max:5000'],
            'pm_activity' => ['nullable', 'string', 'max:5000'],
        ]);

        $journalChanged = $attendance->am_activity !== ($validated['am_activity'] ?? null)
            || $attendance->pm_activity !== ($validated['pm_activity'] ?? null);

        $attendance->update([
            ...$validated,
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
            ...($journalChanged ? [
                'journal_status' => 'pending',
                'journal_feedback' => null,
                'journal_reviewed_by' => null,
                'journal_reviewed_at' => null,
            ] : []),
        ]);

        return response()->json([
            'message' => 'Attendance log updated. Verification was reset for review.',
            'attendance' => $this->recordPayload($attendance->fresh()->load(['student.user', 'student.program', 'student.hte', 'verifier', 'journalReviewer'])),
        ]);
    }

    public function verify(Request $request, Attendance $attendance)
    {
        ProgramAccess::authorizeAttendance($request->user(), $attendance);
        $validated = $request->validate(['verified' => ['sometimes', 'boolean']]);
        $verified = $validated['verified'] ?? true;

        $attendance->update([
            'is_verified' => $verified,
            'verified_by' => $verified ? $request->user()->id : null,
            'verified_at' => $verified ? now() : null,
        ]);

        return response()->json([
            'message' => $verified ? 'Attendance log verified.' : 'Attendance verification removed.',
            'attendance' => $this->recordPayload($attendance->fresh()->load(['student.user', 'student.program', 'student.hte', 'verifier', 'journalReviewer'])),
        ]);
    }

    public function reviewJournal(Request $request, Attendance $attendance)
    {
        ProgramAccess::authorizeAttendance($request->user(), $attendance);
        if (! $this->hasJournal($attendance)) {
            throw ValidationException::withMessages(['journal' => 'This attendance record has no journal entry to review.']);
        }

        $validated = $request->validate([
            'decision' => ['prohibited'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $attendance->update([
            'journal_status' => 'reviewed',
            'journal_feedback' => $validated['feedback'] ?? null,
            'journal_reviewed_by' => $request->user()->id,
            'journal_reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Daily journal marked as reviewed.',
            'attendance' => $this->recordPayload($attendance->fresh()->load(['student.user', 'student.program', 'student.hte', 'verifier', 'journalReviewer'])),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->search, function (Builder $builder, string $search) {
                $builder->whereHas('student', function (Builder $student) use ($search) {
                    $student->where('student_id', 'like', '%'.$search.'%')
                        ->orWhere('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%');
                });
            })
            ->when($request->status, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($request->verification, fn (Builder $builder, string $verification) => $builder->where('is_verified', $verification === 'verified'))
            ->when($request->date_from, fn (Builder $builder, string $date) => $builder->whereDate('date', '>=', $date))
            ->when($request->date_to, fn (Builder $builder, string $date) => $builder->whereDate('date', '<=', $date))
            ->when($request->journal, function (Builder $builder, string $journal) {
                if (in_array($journal, ['pending', 'reviewed'], true)) {
                    $builder->where('journal_status', $journal)
                        ->where(fn (Builder $nested) => $nested->whereNotNull('am_activity')->orWhereNotNull('pm_activity'));
                } elseif ($journal === 'submitted') {
                    $builder->where(fn (Builder $nested) => $nested->whereNotNull('am_activity')->orWhereNotNull('pm_activity'));
                } else {
                    $builder->whereIn('status', ['present', 'late'])
                        ->whereNull('am_activity')
                        ->whereNull('pm_activity');
                }
            });
    }

    private function recordPayload(Attendance $attendance): array
    {
        $regularHours = $this->regularHours($attendance);

        return [
            ...$attendance->toArray(),
            'regular_hours' => round($regularHours, 2),
            'total_hours' => round($regularHours + (float) $attendance->overtime_hours, 2),
            'journal_submitted' => $this->hasJournal($attendance),
            'journal_complete' => $this->journalComplete($attendance),
        ];
    }

    /**
     * Aggregate the filtered result one row at a time instead of materializing
     * the full history and its relations in PHP memory.
     */
    private function summarize(Builder $query): array
    {
        $statusColors = ['present' => '#16a34a', 'late' => '#d97706', 'absent' => '#dc2626', 'holiday' => '#7c3aed'];
        $modeLabels = ['wfo' => 'WFO', 'wfh' => 'WFH', 'field' => 'Field Work'];

        $statuses = array_fill_keys(array_keys($statusColors), 0);
        $modes = array_fill_keys(array_keys($modeLabels), 0);
        $daily = [];
        $total = $verified = $journalsSubmitted = $journalsPending = 0;
        $totalHours = 0.0;

        foreach ($query->cursor() as $record) {
            $total++;
            $statuses[$record->status] = ($statuses[$record->status] ?? 0) + 1;
            $modes[$record->work_mode] = ($modes[$record->work_mode] ?? 0) + 1;
            $verified += $record->is_verified ? 1 : 0;
            $totalHours += $this->renderedHours($record);

            if ($this->hasJournal($record)) {
                $journalsSubmitted++;
                $journalsPending += $record->journal_status === 'pending' ? 1 : 0;
            }

            $date = $record->date->toDateString();
            $daily[$date] ??= ['hours' => 0.0, 'records' => 0];
            $daily[$date]['hours'] += $this->renderedHours($record);
            $daily[$date]['records']++;
        }

        ksort($daily);
        $daily = array_slice($daily, -14, null, true);
        $workdays = $statuses['present'] + $statuses['late'] + $statuses['absent'];

        return [
            'total' => $total,
            'statuses' => $statuses,
            'verified' => $verified,
            'pending_verification' => $total - $verified,
            'attendance_rate' => $workdays > 0 ? round((($statuses['present'] + $statuses['late']) / $workdays) * 100, 1) : 0,
            'total_hours' => round($totalHours, 1),
            'journals_submitted' => $journalsSubmitted,
            'journals_pending' => $journalsPending,
            'analytics' => [
                'status_breakdown' => collect($statusColors)->map(fn (string $color, string $status) => [
                    'name' => ucfirst($status),
                    'value' => $statuses[$status],
                    'color' => $color,
                ])->values(),
                'work_modes' => collect($modeLabels)->map(fn (string $label, string $mode) => [
                    'name' => $label,
                    'value' => $modes[$mode],
                ])->values(),
                'daily_hours' => collect($daily)->map(fn (array $item, string $date) => [
                    'date' => $date,
                    'hours' => round($item['hours'], 1),
                    'records' => $item['records'],
                ])->values(),
            ],
        ];
    }

    private function hasJournal(Attendance $attendance): bool
    {
        return filled($attendance->am_activity) || filled($attendance->pm_activity);
    }

    private function journalComplete(Attendance $attendance): bool
    {
        if (! in_array($attendance->status, ['present', 'late'], true)) {
            return true;
        }

        return match ($attendance->session_type) {
            'am_half' => filled($attendance->am_activity),
            'pm_half' => filled($attendance->pm_activity),
            default => filled($attendance->am_activity) && filled($attendance->pm_activity),
        };
    }

    private function regularHours(Attendance $attendance): float
    {
        return AttendanceHours::regular($attendance);
    }

    private function renderedHours(Attendance $attendance): float
    {
        return AttendanceHours::total($attendance);
    }
}
