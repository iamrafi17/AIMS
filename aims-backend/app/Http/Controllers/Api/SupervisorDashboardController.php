<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\Student;
use Illuminate\Http\Request;

class SupervisorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $supervisorId = $request->user()->id;
        $evaluations = Evaluation::where('supervisor_id', $supervisorId)->get();
        $studentIds = $evaluations->pluck('student_id')->unique()->values();

        $students = Student::with([
            'user',
            'program',
            'hte',
            'attendance',
            'evaluations' => fn ($query) => $query
                ->where('supervisor_id', $supervisorId)
                ->latest(),
        ])->whereIn('id', $studentIds)->get();

        $monthAttendance = Attendance::whereIn('student_id', $studentIds)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->get();
        $recentAttendance = Attendance::whereIn('student_id', $studentIds)
            ->whereDate('date', '>=', now()->subDays(6)->toDateString())
            ->get();

        $present = $monthAttendance->where('status', 'present')->count();
        $late = $monthAttendance->where('status', 'late')->count();
        $absent = $monthAttendance->where('status', 'absent')->count();
        $holiday = $monthAttendance->where('status', 'holiday')->count();
        $attended = $present + $late;
        $workdays = $attended + $absent;
        $attendanceRate = $workdays > 0 ? ($attended / $workdays) * 100 : 0;

        $interns = $students
            ->map(fn (Student $student) => $this->studentPayload($student))
            ->sortBy([
                fn (array $left, array $right) => $this->statusRank($left['internship_status']) <=> $this->statusRank($right['internship_status']),
                fn (array $left, array $right) => $right['progress'] <=> $left['progress'],
            ])
            ->values();

        $pendingEvaluations = $students
            ->flatMap(fn (Student $student) => $this->pendingEvaluationPayloads($student))
            ->values();

        $activeInterns = $interns->where('internship_status', 'active');
        $completed = $interns->where('internship_status', 'completed')->count();
        $nearCompletion = $activeInterns->where('progress', '>=', 80)->count();
        $dropped = $interns->where('internship_status', 'dropped')->count();
        $notStarted = $interns
            ->whereNotIn('internship_status', ['completed', 'dropped'])
            ->where('progress', '<=', 0)
            ->count();
        $inProgress = $interns->count() - $completed - $nearCompletion - $notStarted - $dropped;
        $averageProgress = $interns->avg('progress') ?? 0;

        $attendanceTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($recentAttendance) {
            $date = now()->subDays($daysAgo);
            $records = $recentAttendance->filter(fn (Attendance $record) => $record->date->isSameDay($date));
            $attended = $records->whereIn('status', ['present', 'late'])->count();
            $workdays = $attended + $records->where('status', 'absent')->count();

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'rate' => $workdays > 0 ? round(($attended / $workdays) * 100, 1) : 0,
                'records' => $records->count(),
            ];
        })->values();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'overview' => [
                'assigned' => $students->count(),
                'active' => $students->where('internship_status', 'active')->count(),
                'completed' => $completed,
                'pending_evaluations' => $pendingEvaluations->count(),
                'average_progress' => round($averageProgress, 1),
            ],
            'assigned_interns' => $interns,
            'attendance' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'holiday' => $holiday,
                'total' => $monthAttendance->count(),
                'rate' => round($attendanceRate, 1),
                'verified' => $monthAttendance->where('is_verified', true)->count(),
                'total_hours' => round(
                    $monthAttendance->sum(fn (Attendance $record) => $this->renderedHours($record)),
                    1
                ),
                'trend' => $attendanceTrend,
            ],
            'progress' => [
                'average' => round($averageProgress, 1),
                'on_track' => $activeInterns->where('progress', '>=', 75)->count(),
                'progressing' => $activeInterns->whereBetween('progress', [40, 74.99])->count(),
                'needs_attention' => $activeInterns->where('progress', '<', 40)->count(),
                'total_rendered_hours' => round($interns->sum('rendered_hours'), 1),
                'total_required_hours' => round($interns->sum('required_hours'), 1),
            ],
            'pending_evaluations' => [
                'total' => $pendingEvaluations->count(),
                'midterm' => $pendingEvaluations->where('evaluation_type', 'midterm')->count(),
                'final' => $pendingEvaluations->where('evaluation_type', 'final')->count(),
                'items' => $pendingEvaluations,
            ],
            'completion' => [
                'completed' => $completed,
                'near_completion' => $nearCompletion,
                'in_progress' => max($inProgress, 0),
                'not_started' => $notStarted,
                'dropped' => $dropped,
                'completion_rate' => $interns->count() > 0 ? round(($completed / $interns->count()) * 100, 1) : 0,
            ],
        ]);
    }

    private function studentPayload(Student $student): array
    {
        $renderedHours = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));
        $requiredHours = max((float) $student->required_ojt_hours, 0);
        $progress = $requiredHours > 0 ? min(($renderedHours / $requiredHours) * 100, 100) : 0;
        $monthAttendance = $student->attendance
            ->filter(fn (Attendance $record) => $record->date->month === now()->month
                && $record->date->year === now()->year);
        $attended = $monthAttendance->whereIn('status', ['present', 'late'])->count();
        $workdays = $attended + $monthAttendance->where('status', 'absent')->count();

        return [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'name' => trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->join(' ')),
            'avatar_url' => $student->user?->avatar_url,
            'program' => $student->program?->code ?? 'Unassigned',
            'hte' => $student->hte?->name ?? 'Not deployed',
            'internship_status' => $student->internship_status,
            'rendered_hours' => round($renderedHours, 1),
            'required_hours' => round($requiredHours, 1),
            'progress' => round($progress, 1),
            'attendance_rate' => $workdays > 0 ? round(($attended / $workdays) * 100, 1) : 0,
            'midterm_status' => $student->evaluations->firstWhere('evaluation_type', 'midterm')?->status ?? 'not_started',
            'final_status' => $student->evaluations->firstWhere('evaluation_type', 'final')?->status ?? 'not_started',
        ];
    }

    private function pendingEvaluationPayloads(Student $student)
    {
        return collect(['midterm', 'final'])
            ->map(function (string $type) use ($student) {
                $evaluation = $student->evaluations->firstWhere('evaluation_type', $type);

                if ($evaluation && in_array($evaluation->status, ['submitted', 'finalized'], true)) {
                    return null;
                }

                return [
                    'id' => $evaluation?->id,
                    'student_id' => $student->id,
                    'student_number' => $student->student_id,
                    'student_name' => trim($student->first_name.' '.$student->last_name),
                    'program' => $student->program?->code ?? 'Unassigned',
                    'evaluation_type' => $type,
                    'status' => $evaluation?->status ?? 'not_started',
                    'progress' => $this->progress($student),
                ];
            })
            ->filter();
    }

    private function progress(Student $student): float
    {
        $required = max((float) $student->required_ojt_hours, 0);
        $rendered = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));

        return $required > 0 ? round(min(($rendered / $required) * 100, 100), 1) : 0;
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'active' => 1,
            'pending' => 2,
            'completed' => 3,
            default => 4,
        };
    }

    private function renderedHours(Attendance $attendance): float
    {
        $regular = collect([
            [$attendance->am_time_in, $attendance->am_time_out],
            [$attendance->pm_time_in, $attendance->pm_time_out],
        ])->sum(fn (array $slot) => $slot[0] && $slot[1] ? $slot[0]->diffInSeconds($slot[1]) / 3600 : 0);

        if ($regular <= 0 && ! $attendance->am_time_in && ! $attendance->pm_time_in && $attendance->time_in && $attendance->time_out) {
            $regular = $attendance->time_in->diffInSeconds($attendance->time_out) / 3600;
        }

        return $regular + (float) $attendance->overtime_hours;
    }
}
