<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\College;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentLiveLocation;
use App\Support\AttendanceHours;
use App\Support\DeanAccess;
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
            'journal' => ['nullable', 'in:submitted,missing,pending,reviewed'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        // Keep the summary query lean.  Loading relations for every matching
        // attendance record becomes expensive as a program accumulates history.
        $query = $this->applyFilters(
            Attendance::query()
                ->whereHas('student', fn (Builder $student) => $student->whereIn('students.id', ProgramAccess::studentQuery($request->user())->select('students.id'))),
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

    public function liveLocations(Request $request)
    {
        return $this->liveLocationsResponse($request, 'coordinator');
    }

    public function universityLiveLocations(Request $request)
    {
        return $this->liveLocationsResponse($request, 'vpaa');
    }

    public function collegeLiveLocations(Request $request)
    {
        return $this->liveLocationsResponse($request, 'dean');
    }

    private function liveLocationsResponse(Request $request, string $scope)
    {
        $now = now();
        $collegeId = $scope === 'dean' ? DeanAccess::collegeId($request->user()) : null;
        $activeAttendance = Attendance::query()
            ->whereDate('attendance.date', $now->toDateString())
            ->whereIn('attendance.status', ['present', 'late'])
            ->where(fn (Builder $session) => $session
                ->where(fn (Builder $am) => $am
                    ->whereHas('events', fn (Builder $events) => $events->where('event_type', 'am_in'))
                    ->whereDoesntHave('events', fn (Builder $events) => $events->where('event_type', 'am_out')))
                ->orWhere(fn (Builder $pm) => $pm
                    ->whereHas('events', fn (Builder $events) => $events->where('event_type', 'pm_in'))
                    ->whereDoesntHave('events', fn (Builder $events) => $events->where('event_type', 'pm_out'))));

        $sessions = $activeAttendance
            ->whereHas('student', fn (Builder $student) => match ($scope) {
                'dean' => $student->forCollege($collegeId),
                'vpaa' => $student,
                default => $student->whereIn('students.id', ProgramAccess::studentQuery($request->user())->select('students.id')),
            })
            ->whereHas('student.user', fn (Builder $user) => $user->where('is_active', true))
            ->with(['student.program.college', 'student.hte', 'student.liveLocation'])
            ->get();

        $students = $sessions->map(function (Attendance $attendance) use ($now) {
            $student = $attendance->student;
            $timeIn = $attendance->pm_time_in && ! $attendance->pm_time_out
                ? $attendance->pm_time_in : $attendance->am_time_in;
            $location = $student->liveLocation;
            // A location from an earlier session must never be presented as this session's position.
            if ($location && (($location->attendance_id && $location->attendance_id !== $attendance->id)
                || ($location->student_internship_id && $location->student_internship_id !== $attendance->student_internship_id)
                || (! $location->attendance_id && $timeIn && $location->created_at->lt($timeIn->copy()->subSecond())))) {
                $location = null;
            }
            $location?->setRelation('student', $student);
            $age = $location ? max(0, $location->captured_at->diffInSeconds($now, false)) : null;
            $status = ! $location ? 'unavailable' : ($age <= config('attendance.gps_online_seconds', 30)
                ? 'online' : ($age <= config('attendance.gps_stale_seconds', 120) ? 'delayed' : 'stale'));

            return [
                'student_id' => $student->id,
                'school_id' => $student->student_id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'college_id' => $student->program?->college_id,
                'college' => $student->program?->college?->code,
                'program_id' => $student->program?->id,
                'program' => $student->program?->code,
                'hte_id' => $student->hte?->id,
                'hte' => $student->hte?->name,
                'time_in' => $timeIn?->toIso8601String(),
                'gps_status' => $status,
                'location' => $location ? $this->liveLocationPayload($location) : null,
            ];
        })->values();
        $locations = $students->pluck('location')->filter()->values();

        $totalStudents = (match ($scope) {
            'dean' => Student::query()->forCollege($collegeId),
            'vpaa' => Student::query(),
            default => ProgramAccess::studentQuery($request->user()),
        })
            ->whereHas('user', fn (Builder $user) => $user->where('is_active', true))
            ->count();

        $filterOptions = $scope === 'coordinator' ? null : [
            'colleges' => College::query()->when($collegeId, fn (Builder $query) => $query->whereKey($collegeId))
                ->orderBy('name')->get(['id', 'name', 'code']),
            'programs' => Program::query()->when($collegeId, fn (Builder $query) => $query->where('college_id', $collegeId))
                ->orderBy('code')->get(['id', 'college_id', 'name', 'code']),
        ];

        return response()->json([
            'server_time' => $now->toIso8601String(),
            'sharing' => $locations->count(),
            'total_students' => $totalStudents,
            ...($filterOptions ? ['filter_options' => $filterOptions] : []),
            'locations' => $locations,
            'active_students' => $students,
            'summary' => [
                'timed_in' => $students->count(),
                'online' => $students->where('gps_status', 'online')->count(),
                'delayed' => $students->where('gps_status', 'delayed')->count(),
                'stale' => $students->where('gps_status', 'stale')->count(),
                'unavailable' => $students->where('gps_status', 'unavailable')->count(),
            ],
            'htes' => $students->filter(fn ($student) => $student['hte_id'])
                ->unique('hte_id')->map(fn ($student) => ['id' => $student['hte_id'], 'name' => $student['hte']])->values(),
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
                    $student->where('students.student_id', 'like', '%'.$search.'%')
                        ->orWhere('students.first_name', 'like', '%'.$search.'%')
                        ->orWhere('students.last_name', 'like', '%'.$search.'%');
                });
            })
            ->when($request->status, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($request->date_from, fn (Builder $builder, string $date) => $builder->whereDate('date', '>=', $date))
            ->when($request->date_to, fn (Builder $builder, string $date) => $builder->whereDate('date', '<=', $date))
            ->when($request->journal, function (Builder $builder, string $journal) {
                if (in_array($journal, ['pending', 'reviewed'], true)) {
                    $builder->withJournalStatus($journal)->hasJournals();
                } elseif ($journal === 'submitted') {
                    $builder->hasJournals();
                } else {
                    $builder->whereIn('status', ['present', 'late'])
                        ->withoutJournals();
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
        $total = $journalsSubmitted = $journalsPending = 0;
        $totalHours = 0.0;

        foreach ($query->cursor() as $record) {
            $total++;
            $statuses[$record->status] = ($statuses[$record->status] ?? 0) + 1;
            $modes[$record->work_mode] = ($modes[$record->work_mode] ?? 0) + 1;
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
            'attendance_rate' => $workdays > 0 ? round((($statuses['present'] + $statuses['late']) / $workdays) * 100, 1) : 0,
            'total_hours' => round($totalHours, 2),
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

    private function liveLocationPayload(StudentLiveLocation $location): array
    {
        $hte = $location->student->hte;
        $distance = null;
        $radius = null;
        $outsideGeofence = false;
        $geofenceStatus = 'unavailable';

        if ($hte?->geofence_enabled && $hte->latitude !== null && $hte->longitude !== null && $hte->geofence_radius > 0) {
            $radius = (int) $hte->geofence_radius;
            $distance = (int) round($this->calculateDistance(
                $location->latitude,
                $location->longitude,
                (float) $hte->latitude,
                (float) $hte->longitude,
            ));
            $outsideGeofence = $distance > $radius;
            if ($location->accuracy !== null) {
                $accuracy = (float) $location->accuracy;
                $geofenceStatus = $distance - $accuracy > $radius ? 'outside'
                    : ($distance + $accuracy <= $radius ? 'inside' : 'uncertain');
            } else {
                $geofenceStatus = 'uncertain';
            }
        }

        return [
            'id' => $location->id,
            'student_id' => $location->student->id,
            'school_id' => $location->student->student_id,
            'name' => trim($location->student->first_name.' '.$location->student->last_name),
            'program' => $location->student->program?->code,
            'hte' => $hte?->name,
            'hte_address' => $hte?->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'accuracy' => $location->accuracy,
            'captured_at' => $location->captured_at->toIso8601String(),
            'recorded_at' => $location->recorded_at?->toIso8601String(),
            'received_at' => $location->received_at?->toIso8601String(),
            'status' => $outsideGeofence ? 'outside_geofence' : 'timed_in',
            'outside_geofence' => $outsideGeofence,
            'geofence_status' => $geofenceStatus,
            'distance_from_hte' => $distance,
            'geofence_radius' => $radius,
        ];
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
