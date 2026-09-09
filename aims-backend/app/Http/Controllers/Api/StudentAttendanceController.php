<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentLiveLocation;
use App\Support\AttendanceHours;
use App\Support\ReverseGeocoder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;

        $attendance = Attendance::where('student_id', $student->id)
            ->when($request->month, function ($query) use ($request) {
                $query->whereMonth('date', $request->month);
            })
            ->when($request->year, function ($query) use ($request) {
                $query->whereYear('date', $request->year);
            })
            ->latest('date')
            ->paginate(15);

        return response()->json($attendance);
    }

    public function quickClock(Request $request)
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        $attendance = Attendance::where('student_id', $student->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return response()->json($this->quickClockPayload($attendance));
    }

    public function shareLiveLocation(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'between:0,10000'],
        ]);

        $student = $request->user()->student;
        abort_unless($student, 404, 'Student profile not found.');

        $attendance = Attendance::where('student_id', $student->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        abort_unless($attendance?->status === 'present', 422, 'Live monitoring starts only after a successful Time In.');
        $nextSlot = collect($this->slotSequence($attendance->session_type))
            ->first(fn (string $slot) => $attendance->{$slot} === null);
        abort_unless($nextSlot && str_ends_with($nextSlot, 'time_out'), 422, 'There is no active timed-in session to monitor.');

        $distance = null;
        $outsideGeofence = false;
        $geofenceRadius = null;
        if (
            $attendance->work_mode === 'wfo'
            && $student->hte
            && $student->hte->geofence_enabled
            && $student->hte->latitude !== null
            && $student->hte->longitude !== null
            && $student->hte->geofence_radius > 0
        ) {
            $geofenceRadius = (int) $student->hte->geofence_radius;
            $distance = (int) round($this->calculateDistance(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $student->hte->latitude,
                (float) $student->hte->longitude,
            ));
            $outsideGeofence = $distance > $geofenceRadius;
        }

        $location = StudentLiveLocation::updateOrCreate(
            ['student_id' => $student->id],
            [
                'latitude' => (float) $validated['latitude'],
                'longitude' => (float) $validated['longitude'],
                'accuracy' => isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
                'captured_at' => now(),
            ],
        );

        return response()->json([
            'message' => $outsideGeofence
                ? "Warning: you are {$distance} meters from the HTE and outside the {$geofenceRadius}-meter allowed radius. Return closer to your HTE."
                : 'Live timed-in location updated.',
            'captured_at' => $location->captured_at->toIso8601String(),
            'outside_geofence' => $outsideGeofence,
            'distance_from_hte' => $distance,
            'geofence_radius' => $geofenceRadius,
        ]);
    }

    public function workspace(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $student = $request->user()->student;
        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $selectedDate = Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay();
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        $workDays = $this->effectiveWorkDays($student);

        $calendarRecords = Attendance::where('student_id', $student->id)
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->get();

        $weekRecords = Attendance::where('student_id', $student->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        $selectedAttendance = Attendance::where('student_id', $student->id)
            ->whereDate('date', $selectedDate->toDateString())
            ->first();

        $geofenceActive = $student->hte
            && $student->hte->geofence_enabled
            && $student->hte->latitude !== null
            && $student->hte->longitude !== null
            && $student->hte->geofence_radius > 0;

        return response()->json([
            'server_date' => now()->toDateString(),
            'timezone' => config('app.timezone'),
            'week_summary' => [
                'rendered_hours' => round($weekRecords->sum(fn (Attendance $record) => AttendanceHours::total($record)), 2),
                'overtime_hours' => round($weekRecords->sum(fn (Attendance $record) => (float) $record->overtime_hours), 2),
                'days_present' => $weekRecords->whereIn('status', ['present', 'late'])->count(),
                'days_left' => $this->remainingWorkdaysThisWeek($student, $workDays),
            ],
            'calendar' => $calendarRecords->map(fn (Attendance $record) => [
                'date' => $record->date->toDateString(),
                'status' => $record->status,
                'hours' => round(AttendanceHours::total($record), 2),
                'regular_hours' => round($this->regularHours($record), 2),
                'overtime_hours' => (float) $record->overtime_hours,
            ])->values(),
            'selected_attendance' => $this->workspaceAttendance($selectedAttendance),
            'settings' => [
                'allow_past_attendance' => $student->allow_past_attendance,
                'ojt_start_date' => $student->ojt_start_date?->toDateString(),
                'ojt_end_date' => $student->ojt_end_date?->toDateString(),
                'work_days' => $workDays,
                'official_schedule' => [
                    'am_start' => substr($student->official_am_start, 0, 5),
                    'am_end' => substr($student->official_am_end, 0, 5),
                    'pm_start' => substr($student->official_pm_start, 0, 5),
                    'pm_end' => substr($student->official_pm_end, 0, 5),
                ],
                'geofence' => $geofenceActive ? [
                    'active' => true,
                    'hte' => $student->hte->name,
                    'radius' => $student->hte->geofence_radius,
                ] : ['active' => false],
            ],
        ]);
    }

    public function saveEntry(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'in:present,absent,holiday'],
            'work_mode' => ['nullable', 'in:wfo,wfh,field'],
            'session_type' => ['nullable', 'in:full_day,am_half,pm_half'],
            'am_time_in' => ['nullable', 'date_format:H:i'],
            'am_time_out' => ['nullable', 'date_format:H:i'],
            'pm_time_in' => ['nullable', 'date_format:H:i'],
            'pm_time_out' => ['nullable', 'date_format:H:i'],
            'ot_start' => ['nullable', 'date_format:H:i'],
            'ot_end' => ['nullable', 'date_format:H:i'],
            'am_activity' => ['nullable', 'string', 'max:2000'],
            'pm_activity' => ['nullable', 'string', 'max:2000'],
        ]);

        $student = $request->user()->student;
        $date = Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay();
        $today = now()->startOfDay();

        if ($date->gt($today)) {
            throw ValidationException::withMessages(['date' => 'Future attendance dates cannot be edited.']);
        }

        $isToday = $date->isSameDay($today);
        if (! $isToday && ! $student->allow_past_attendance) {
            return response()->json(['message' => 'Past attendance logging is disabled by your administrator.'], 403);
        }

        if (($student->ojt_start_date && $date->lt($student->ojt_start_date))
            || ($student->ojt_end_date && $date->gt($student->ojt_end_date))) {
            throw ValidationException::withMessages(['date' => 'This date is outside your configured OJT period.']);
        }

        $attendance = Attendance::where('student_id', $student->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($isToday && ! $attendance) {
            throw ValidationException::withMessages(['date' => 'Use the Smart Clock to begin today\'s attendance.']);
        }

        $editableTodayStatus = $attendance?->status === 'late' ? 'present' : $attendance?->status;
        if ($isToday && $validated['status'] !== $editableTodayStatus) {
            throw ValidationException::withMessages(['status' => 'Today\'s attendance status cannot be changed here.']);
        }

        $status = $isToday ? $attendance->status : $validated['status'];
        $isWorkday = in_array($status, ['present', 'late'], true);
        $sessionType = $isToday ? $attendance->session_type : ($validated['session_type'] ?? 'full_day');
        $workMode = $isToday ? $attendance->work_mode : ($validated['work_mode'] ?? 'wfo');

        if (! $isToday && $status === 'present') {
            $this->validatePastPresentEntry($validated, $sessionType);
        }

        $updates = [
            'status' => $status,
            'work_mode' => $workMode,
            'session_type' => $sessionType,
            'am_activity' => $validated['am_activity'] ?? null,
            'pm_activity' => $validated['pm_activity'] ?? null,
        ];

        if (! $isToday) {
            foreach (['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out'] as $field) {
                $updates[$field] = $status === 'present' && ! empty($validated[$field])
                    ? $this->dateTime($date, $validated[$field])
                    : null;
            }

            $updates['time_in'] = $updates['am_time_in'] ?? $updates['pm_time_in'];
            $updates['time_out'] = $updates['pm_time_out'] ?? $updates['am_time_out'];
        }

        $warnings = [];
        if ($isWorkday && ! empty($validated['ot_start'])) {
            $otStart = $this->dateTime($date, $validated['ot_start']);
            $finalTimeOut = $attendance?->pm_time_out ?? $attendance?->am_time_out;
            $otEnd = $isToday
                ? $finalTimeOut
                : (! empty($validated['ot_end']) ? $this->dateTime($date, $validated['ot_end']) : null);

            if (! $otEnd) {
                throw ValidationException::withMessages(['ot_end' => $isToday
                    ? 'Complete your final time-out before recording overtime.'
                    : 'OT End is required when OT Start is entered.']);
            }

            [$overtimeHours, $otWarnings] = $this->calculateOvertime($student, $date, $otStart, $otEnd, $sessionType);
            $updates['ot_start'] = $otStart;
            $updates['ot_end'] = $otEnd;
            $updates['overtime_hours'] = $overtimeHours;
            $warnings = $otWarnings;
        } else {
            $updates['ot_start'] = null;
            $updates['ot_end'] = null;
            $updates['overtime_hours'] = 0;
        }

        if (! $isWorkday) {
            foreach (['time_in', 'time_out', 'am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out', 'am_activity', 'pm_activity', 'ot_start', 'ot_end'] as $field) {
                $updates[$field] = null;
            }
            $updates['overtime_hours'] = 0;
        }

        if ($attendance) {
            $attendance->update($updates);
        } else {
            $attendance = Attendance::create([
                'student_id' => $student->id,
                'date' => $date->toDateString(),
                ...$updates,
            ]);
        }

        return response()->json([
            'message' => $isToday ? 'Accomplishments and overtime updated.' : 'Attendance saved for '.$date->format('M j, Y').'.',
            'warnings' => $warnings,
            'attendance' => $this->workspaceAttendance($attendance),
        ]);
    }

    public function smartLog(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:present,absent,holiday',
            'work_mode' => 'required_if:status,present|nullable|in:wfo,wfh,field',
            'session_type' => 'required_if:status,present|nullable|in:full_day,am_half,pm_half',
            'latitude' => 'required_if:status,present|nullable|numeric|between:-90,90',
            'longitude' => 'required_if:status,present|nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|between:0,10000',
            'activity' => 'nullable|string|max:2000',
            'client_time' => 'nullable|date',
        ]);

        $student = $request->user()->student;

        if (! $student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        $usesServerTime = (bool) config('attendance.use_server_time', true);
        if (! $usesServerTime && $validated['status'] === 'present' && empty($validated['client_time'])) {
            throw ValidationException::withMessages([
                'client_time' => 'Device time is required while Smart Clock test mode is enabled.',
            ]);
        }

        $today = now()->toDateString();

        if (in_array($validated['status'], ['absent', 'holiday'], true)) {
            $existing = Attendance::where('student_id', $student->id)
                ->whereDate('date', $today)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Today\'s attendance has already been submitted and can no longer be changed.',
                ], 422);
            }

            $attendance = Attendance::create([
                'student_id' => $student->id,
                'date' => $today,
                'status' => $validated['status'],
                'work_mode' => 'wfo',
                'session_type' => 'full_day',
            ]);
            StudentLiveLocation::where('student_id', $student->id)->delete();

            return response()->json([
                'message' => $validated['status'] === 'absent'
                    ? 'Attendance saved as absent.'
                    : 'Attendance saved as holiday / no work.',
                ...$this->quickClockPayload($attendance),
            ]);
        }

        $recordedTime = $usesServerTime
            ? now()
            : Carbon::parse($validated['client_time'])->setTimezone(config('app.timezone'));

        if (
            $validated['work_mode'] === 'wfo'
            && $student->hte
            && $student->hte->geofence_enabled
            && $student->hte->latitude !== null
            && $student->hte->longitude !== null
            && $student->hte->geofence_radius > 0
        ) {
            $distance = $this->calculateDistance(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $student->hte->latitude,
                (float) $student->hte->longitude,
            );

            if ($distance > $student->hte->geofence_radius) {
                return response()->json([
                    'message' => sprintf(
                        'You are outside the %d-meter HTE geofence (current distance: %d meters).',
                        $student->hte->geofence_radius,
                        round($distance),
                    ),
                ], 422);
            }
        }

        $location = [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'address' => ReverseGeocoder::describe(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
            ),
        ];

        $result = DB::transaction(function () use ($student, $today, $validated, $location, $recordedTime) {
            $attendance = Attendance::where('student_id', $student->id)
                ->whereDate('date', $today)
                ->lockForUpdate()
                ->first();

            if ($attendance && $attendance->status !== 'present') {
                return ['error' => 'Today\'s status has already been submitted and cannot be changed.'];
            }

            if ($attendance && (
                $attendance->work_mode !== $validated['work_mode']
                || $attendance->session_type !== $validated['session_type']
            )) {
                return ['error' => 'Work mode and session type are locked after your first time log.'];
            }

            if (! $attendance) {
                $attendance = Attendance::create([
                    'student_id' => $student->id,
                    'date' => $today,
                    'status' => 'present',
                    'work_mode' => $validated['work_mode'],
                    'session_type' => $validated['session_type'],
                ]);
            }

            $sequence = $this->slotSequence($attendance->session_type);
            $nextSlot = collect($sequence)->first(fn ($slot) => $attendance->{$slot} === null);

            if (! $nextSlot) {
                return ['error' => 'All required time slots are already complete for today.'];
            }

            $isTimeOut = str_ends_with($nextSlot, 'time_out');

            if ($isTimeOut && blank($validated['activity'] ?? null)) {
                return ['error' => 'Please describe your activities before timing out.', 'activity_required' => true];
            }

            $updates = [
                $nextSlot => $recordedTime,
                $nextSlot.'_location' => $location,
            ];

            if ($isTimeOut) {
                $updates[str_starts_with($nextSlot, 'am_') ? 'am_activity' : 'pm_activity'] = trim($validated['activity']);
            }

            if ($attendance->time_in === null && str_ends_with($nextSlot, 'time_in')) {
                $updates['time_in'] = $recordedTime;
                $updates['latitude_in'] = $location['latitude'];
                $updates['longitude_in'] = $location['longitude'];
            }

            if ($nextSlot === end($sequence)) {
                $updates['time_out'] = $recordedTime;
                $updates['latitude_out'] = $location['latitude'];
                $updates['longitude_out'] = $location['longitude'];

                $firstTimeIn = $attendance->am_time_in ?? $attendance->pm_time_in;
                if ($firstTimeIn) {
                    [$overtimeHours] = $this->calculateOvertime(
                        $student,
                        Carbon::parse($today),
                        $firstTimeIn,
                        $recordedTime,
                        $attendance->session_type,
                    );
                    $updates['ot_start'] = $overtimeHours > 0 ? $firstTimeIn : null;
                    $updates['ot_end'] = $overtimeHours > 0 ? $recordedTime : null;
                    $updates['overtime_hours'] = $overtimeHours;
                }
            }

            $attendance->update($updates);
            $attendance->refresh();

            if (str_ends_with($nextSlot, 'time_in')) {
                StudentLiveLocation::updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                        'accuracy' => $validated['accuracy'] ?? null,
                        'captured_at' => now(),
                    ],
                );
            } else {
                StudentLiveLocation::where('student_id', $student->id)->delete();
            }

            return [
                'attendance' => $attendance,
                'completed' => $nextSlot === end($sequence),
                'recorded_slot' => $nextSlot,
            ];
        });

        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['error'],
                'activity_required' => $result['activity_required'] ?? false,
            ], 422);
        }

        $slotLabel = $this->slotLabel($result['recorded_slot']);

        return response()->json([
            'message' => $result['completed']
                ? $slotLabel.' recorded. Your attendance is complete for today!'
                : $slotLabel.' recorded using '.($usesServerTime ? 'server' : 'device test').' time.',
            'just_completed' => $result['completed'],
            ...$this->quickClockPayload($result['attendance']),
        ]);
    }

    private function validatePastPresentEntry(array $data, string $sessionType): void
    {
        $required = match ($sessionType) {
            'am_half' => ['am_time_in', 'am_time_out', 'am_activity'],
            'pm_half' => ['pm_time_in', 'pm_time_out', 'pm_activity'],
            default => ['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out', 'am_activity', 'pm_activity'],
        };

        $errors = [];
        foreach ($required as $field) {
            if (blank($data[$field] ?? null)) {
                $errors[$field] = str($field)->replace('_', ' ')->title().' is required for a present entry.';
            }
        }

        foreach ([['am_time_in', 'am_time_out'], ['pm_time_in', 'pm_time_out']] as [$start, $end]) {
            if (! empty($data[$start]) && ! empty($data[$end]) && $data[$end] <= $data[$start]) {
                $errors[$end] = str($end)->replace('_', ' ')->title().' must be later than the corresponding time in.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function dateTime(Carbon $date, string $time): Carbon
    {
        return Carbon::parse($date->toDateString().' '.$time, config('app.timezone'));
    }

    private function calculateOvertime(Student $student, Carbon $date, Carbon $start, Carbon $end, string $sessionType = 'full_day'): array
    {
        $minimum = $this->dateTime($date, '06:00');
        $maximum = $this->dateTime($date, '23:00');

        $errors = [];
        if ($end->lte($start)) {
            $errors['ot_end'] = 'OT End must be later than OT Start.';
        }
        if ($start->lt($minimum) || $end->gt($maximum)) {
            $errors['ot_start'] = 'Overtime must be within 6:00 AM and 11:00 PM.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        [$scheduleStartField, $scheduleEndField] = match ($sessionType) {
            'am_half' => ['official_am_start', 'official_am_end'],
            'pm_half' => ['official_pm_start', 'official_pm_end'],
            default => ['official_am_start', 'official_pm_end'],
        };
        $scheduleStart = $this->dateTime($date, substr($student->{$scheduleStartField}, 0, 5));
        $scheduleEnd = $this->dateTime($date, substr($student->{$scheduleEndField}, 0, 5));
        $overlapStart = $start->greaterThan($scheduleStart) ? $start : $scheduleStart;
        $overlapEnd = $end->lessThan($scheduleEnd) ? $end : $scheduleEnd;
        $overlapSeconds = $overlapEnd->gt($overlapStart) ? $overlapStart->diffInSeconds($overlapEnd) : 0;
        $effectiveHours = max(($start->diffInSeconds($end) - $overlapSeconds) / 3600, 0);
        $warnings = [];

        if ($overlapSeconds > 0) {
            $warnings[] = 'OT range overlaps with the official schedule; only time outside the schedule counts. Lunch break is excluded.';
        }
        if ($effectiveHours < 0.5) {
            $warnings[] = 'Effective overtime is under the 30-minute minimum and was recorded as 0 hours.';
            $effectiveHours = 0;
        }

        return [round($effectiveHours, 2), $warnings];
    }

    private function regularHours(Attendance $attendance): float
    {
        return AttendanceHours::regular($attendance);
    }

    private function workspaceAttendance(?Attendance $attendance): ?array
    {
        if (! $attendance) {
            return null;
        }

        return [
            'id' => $attendance->id,
            'date' => $attendance->date->toDateString(),
            'status' => $attendance->status,
            'work_mode' => $attendance->work_mode,
            'session_type' => $attendance->session_type,
            'am_time_in' => $attendance->am_time_in?->format('H:i'),
            'am_time_out' => $attendance->am_time_out?->format('H:i'),
            'pm_time_in' => $attendance->pm_time_in?->format('H:i'),
            'pm_time_out' => $attendance->pm_time_out?->format('H:i'),
            'ot_start' => $attendance->ot_start?->format('H:i'),
            'ot_end' => $attendance->ot_end?->format('H:i'),
            'overtime_hours' => (float) $attendance->overtime_hours,
            'am_activity' => $attendance->am_activity,
            'pm_activity' => $attendance->pm_activity,
            'regular_hours' => round($this->regularHours($attendance), 2),
            'total_hours' => round(AttendanceHours::total($attendance), 2),
        ];
    }

    public function clockIn(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'work_mode' => 'required|in:wfo,wfh,field',
            'session_type' => 'required|in:full_day,am_half,pm_half',
        ]);

        $student = $request->user()->student;
        $today = now()->toDateString();

        // Check if already clocked in
        $existingAttendance = Attendance::where('student_id', $student->id)
            ->whereDate('date', $today)
            ->first();

        if ($existingAttendance) {
            return response()->json(['message' => 'Already clocked in today'], 400);
        }

        // Check geofence if WFO
        if ($request->work_mode === 'wfo' && $student->hte?->geofence_enabled) {
            $distance = $this->calculateDistance(
                $request->latitude,
                $request->longitude,
                $student->hte->latitude,
                $student->hte->longitude
            );

            if ($distance > $student->hte->geofence_radius) {
                return response()->json(['message' => 'Outside geofence area'], 400);
            }
        }

        // Determine status based on time
        $hour = now()->hour;
        $status = ($hour >= 8 && $hour <= 10) ? 'present' : 'late';

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => $today,
            'time_in' => now(),
            'work_mode' => $request->work_mode,
            'session_type' => $request->session_type,
            'status' => $status,
            'latitude_in' => $request->latitude,
            'longitude_in' => $request->longitude,
        ]);

        return response()->json([
            'message' => 'Clock in successful',
            'attendance' => $attendance,
        ]);
    }

    public function clockOut(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $student = $request->user()->student;
        $today = now()->toDateString();

        $attendance = Attendance::where('student_id', $student->id)
            ->whereDate('date', $today)
            ->whereNull('time_out')
            ->first();

        if (! $attendance) {
            return response()->json(['message' => 'No active clock-in found'], 400);
        }

        $attendance->update([
            'time_out' => now(),
            'latitude_out' => $request->latitude,
            'longitude_out' => $request->longitude,
        ]);

        return response()->json([
            'message' => 'Clock out successful',
            'attendance' => $attendance,
        ]);
    }

    private function quickClockPayload(?Attendance $attendance): array
    {
        $sequence = $attendance && $attendance->status === 'present'
            ? $this->slotSequence($attendance->session_type)
            : [];

        $nextSlot = $attendance && $attendance->status === 'present'
            ? collect($sequence)->first(fn ($slot) => $attendance->{$slot} === null)
            : null;

        $slots = collect($sequence)->map(fn ($slot, $index) => [
            'key' => $slot,
            'number' => $index + 1,
            'label' => $this->slotLabel($slot),
            'period' => str_starts_with($slot, 'am_') ? 'AM' : 'PM',
            'type' => str_ends_with($slot, 'time_in') ? 'in' : 'out',
            'completed' => $attendance->{$slot} !== null,
            'time' => $attendance->{$slot}?->toIso8601String(),
            'location' => $attendance->{$slot.'_location'},
        ])->values();

        $isDayStatus = $attendance && in_array($attendance->status, ['absent', 'holiday'], true);
        $completed = $isDayStatus || ($attendance && $sequence && $nextSlot === null);
        $settingsLocked = $attendance !== null;

        return [
            'server_time' => now()->toIso8601String(),
            'server_date' => now()->toDateString(),
            'timezone' => config('app.timezone'),
            'uses_server_time' => (bool) config('attendance.use_server_time', true),
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'status' => $attendance->status,
                'work_mode' => $attendance->work_mode,
                'session_type' => $attendance->session_type,
                'am_activity' => $attendance->am_activity,
                'pm_activity' => $attendance->pm_activity,
                'ot_start' => $attendance->ot_start?->format('H:i'),
                'ot_end' => $attendance->ot_end?->format('H:i'),
                'overtime_hours' => (float) $attendance->overtime_hours,
            ] : null,
            'slots' => $slots,
            'next_action' => $nextSlot ? [
                'key' => $nextSlot,
                'label' => $this->slotLabel($nextSlot),
                'period' => str_starts_with($nextSlot, 'am_') ? 'AM' : 'PM',
                'type' => str_ends_with($nextSlot, 'time_in') ? 'in' : 'out',
                'requires_activity' => str_ends_with($nextSlot, 'time_out'),
            ] : null,
            'settings_locked' => $settingsLocked,
            'completed' => (bool) $completed,
        ];
    }

    private function slotSequence(string $sessionType): array
    {
        return match ($sessionType) {
            'am_half' => ['am_time_in', 'am_time_out'],
            'pm_half' => ['pm_time_in', 'pm_time_out'],
            default => ['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out'],
        };
    }

    private function slotLabel(string $slot): string
    {
        return match ($slot) {
            'am_time_in' => 'Time In (AM)',
            'am_time_out' => 'Time Out (AM)',
            'pm_time_in' => 'Time In (PM)',
            'pm_time_out' => 'Time Out (PM)',
        };
    }

    private function effectiveWorkDays(Student $student): array
    {
        $workDays = $student->work_days ?: $student->hte?->work_days;

        return array_values($workDays ?: ['mon', 'tue', 'wed', 'thu', 'fri']);
    }

    private function remainingWorkdaysThisWeek(Student $student, array $workDays): int
    {
        $dayNames = [
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
        ];

        $cursor = now()->startOfDay()->addDay();
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY)->startOfDay();
        $remaining = 0;

        while ($cursor->lte($weekEnd)) {
            $withinOjtPeriod = (! $student->ojt_start_date || $cursor->gte($student->ojt_start_date))
                && (! $student->ojt_end_date || $cursor->lte($student->ojt_end_date));

            if ($withinOjtPeriod && in_array($dayNames[$cursor->dayOfWeekIso], $workDays, true)) {
                $remaining++;
            }

            $cursor->addDay();
        }

        return $remaining;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
