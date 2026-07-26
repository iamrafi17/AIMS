<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Support\ProgramAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CoordinatorAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:present,late,absent,holiday'],
            'verification' => ['nullable', 'in:verified,pending'],
            'journal' => ['nullable', 'in:submitted,missing,pending,approved,rejected'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = $this->applyFilters(
            Attendance::with(['student.user', 'student.program', 'student.hte', 'verifier', 'journalReviewer'])
                ->whereHas('student', fn (Builder $student) => $student->where('program_id', ProgramAccess::programId($request->user()))),
            $request
        );

        $filteredRecords = (clone $query)->get();
        $records = (clone $query)->latest('date')->latest('id')->paginate(12);
        $records->getCollection()->transform(fn (Attendance $attendance) => $this->recordPayload($attendance));

        $present = $filteredRecords->where('status', 'present')->count();
        $late = $filteredRecords->where('status', 'late')->count();
        $absent = $filteredRecords->where('status', 'absent')->count();
        $workdays = $present + $late + $absent;
        $journalRecords = $filteredRecords->filter(fn (Attendance $record) => $this->hasJournal($record));

        return response()->json([
            ...$records->toArray(),
            'summary' => [
                'total' => $filteredRecords->count(),
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'holiday' => $filteredRecords->where('status', 'holiday')->count(),
                'verified' => $filteredRecords->where('is_verified', true)->count(),
                'pending_verification' => $filteredRecords->where('is_verified', false)->count(),
                'attendance_rate' => $workdays > 0 ? round((($present + $late) / $workdays) * 100, 1) : 0,
                'total_hours' => round($filteredRecords->sum(fn (Attendance $record) => $this->renderedHours($record)), 1),
                'journals_submitted' => $journalRecords->count(),
                'journals_pending' => $journalRecords->where('journal_status', 'pending')->count(),
            ],
            'analytics' => $this->analytics($filteredRecords),
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

    public function destroy(Request $request, Attendance $attendance)
    {
        ProgramAccess::authorizeAttendance($request->user(), $attendance);
        $attendance->delete();

        return response()->json(['message' => 'Attendance log deleted successfully.']);
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
            'decision' => ['required', 'in:approved,rejected'],
            'feedback' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);

        $attendance->update([
            'journal_status' => $validated['decision'],
            'journal_feedback' => $validated['feedback'] ?? null,
            'journal_reviewed_by' => $request->user()->id,
            'journal_reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Daily journal '.$validated['decision'].'.',
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
                if (in_array($journal, ['pending', 'approved', 'rejected'], true)) {
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

    private function analytics($records): array
    {
        $statusColors = ['present' => '#16a34a', 'late' => '#d97706', 'absent' => '#dc2626', 'holiday' => '#7c3aed'];
        $modeLabels = ['wfo' => 'WFO', 'wfh' => 'WFH', 'field' => 'Field Work'];

        return [
            'status_breakdown' => collect($statusColors)->map(fn (string $color, string $status) => [
                'name' => ucfirst($status),
                'value' => $records->where('status', $status)->count(),
                'color' => $color,
            ])->values(),
            'work_modes' => collect($modeLabels)->map(fn (string $label, string $mode) => [
                'name' => $label,
                'value' => $records->where('work_mode', $mode)->count(),
            ])->values(),
            'daily_hours' => $records->groupBy(fn (Attendance $record) => $record->date->toDateString())
                ->sortKeys()
                ->take(-14)
                ->map(fn ($items, string $date) => [
                    'date' => $date,
                    'hours' => round($items->sum(fn (Attendance $record) => $this->renderedHours($record)), 1),
                    'records' => $items->count(),
                ])->values(),
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
        $regular = collect([
            [$attendance->am_time_in, $attendance->am_time_out],
            [$attendance->pm_time_in, $attendance->pm_time_out],
        ])->sum(fn (array $slot) => $slot[0] && $slot[1] ? $slot[0]->diffInSeconds($slot[1]) / 3600 : 0);

        if ($regular <= 0 && ! $attendance->am_time_in && ! $attendance->pm_time_in && $attendance->time_in && $attendance->time_out) {
            return $attendance->time_in->diffInSeconds($attendance->time_out) / 3600;
        }

        return $regular;
    }

    private function renderedHours(Attendance $attendance): float
    {
        return $this->regularHours($attendance) + (float) $attendance->overtime_hours;
    }
}
