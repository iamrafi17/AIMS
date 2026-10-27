<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Models\Student;
use App\Models\TravelLog;
use App\Models\TravelCheckpoint;
use App\Models\TravelReport;
use App\Models\User;
use App\Support\ProgramAccess;
use App\Support\OjtCompletion;
use App\Support\TravelWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CoordinatorTravelController extends Controller
{
    public function index(Request $request)
    {
        ProgramAccess::programId($request->user());
        $sessions = $this->sessions($request)
            ->with(TravelWorkflow::relations())
            ->latest('id')
            ->get();
        $reports = $sessions->pluck('report')->filter();

        return response()->json([
            'summary' => [
                'scheduled_sessions' => $sessions->where('status', 'scheduled')->count(),
                'active_sessions' => $sessions->whereIn('status', ['active', 'traveling', 'arrived'])->count(),
                'completed_sessions' => $sessions->where('status', 'completed')->count(),
                'pending_reports' => $reports->whereIn('status', ['pending_program_head', 'pending_vpaa'])->count(),
                'validated_reports' => $reports->where('status', 'final_validated')->count(),
            ],
            'sessions' => $sessions->map(fn (TravelLog $session) => TravelWorkflow::sessionPayload($session, '/coordinator/travel'))->values(),
        ]);
    }

    public function students(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:100']]);
        $term = trim((string) $request->query('q', ''));
        $students = ProgramAccess::studentQuery($request->user())
            ->with(['program.college', 'hte', 'hteDepartment'])
            ->where(function ($query) use ($term): void {
                if ($term !== '') {
                    $query->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('student_id', 'like', "%{$term}%");
                    $parts = preg_split('/\s+/', $term);
                    if (count($parts) >= 2) {
                        $query->orWhere(fn ($name) => $name
                            ->where('first_name', 'like', '%'.$parts[0].'%')
                            ->where('last_name', 'like', '%'.end($parts).'%'));
                    }
                }
            })
            ->orderBy('last_name')->limit(100)->get()
            ->filter(fn (Student $student) => in_array($student->internship_status, ['pending', 'active'], true)
                && $student->schedule_status === 'approved'
                && $student->ojt_start_date
                && $student->hte?->is_active
                && (int) TravelWorkflow::coordinatorFor($student)?->id === (int) $request->user()->id)
            ->take(30)->map(fn (Student $student): array => TravelWorkflow::studentPayload($student))->values();

        return response()->json(['students' => $students]);
    }

    public function schedule(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);
        $student = ProgramAccess::studentQuery($request->user())
            ->with(['hte', 'program.college'])->findOrFail($data['student_id']);
        $this->ensureSchedulable($request, $student, $data['scheduled_at']);

        $session = DB::transaction(function () use ($request, $student, $data) {
            $existing = TravelLog::query()->whereHas('participants', fn ($query) => $query->whereKey($student->id))
                ->whereIn('status', ['scheduled', 'active', 'traveling', 'arrived'])->lockForUpdate()->exists();
            if ($existing) {
                throw ValidationException::withMessages(['student_id' => 'This student already has an upcoming or active travel session.']);
            }

            $session = TravelLog::create([
                'created_by' => $request->user()->id,
                'coordinator_id' => $request->user()->id,
                'hte_id' => $student->hte_id,
                'session_code' => 'TRV-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'destination' => $student->hte->address,
                'scheduled_at' => $data['scheduled_at'],
                'status' => 'scheduled',
            ]);
            $session->participants()->attach($student->id);
            return $session;
        });

        SystemNotification::sendToUser($student->user_id, 'Travel scheduled',
            'Your travel to '.$student->hte->name.' is scheduled for '.$session->scheduled_at->format('M j, Y g:i A').'.',
            'travel', '/student/travel');

        return response()->json([
            'message' => 'Travel scheduled and the student was notified.',
            'session' => TravelWorkflow::sessionPayload($session->load(TravelWorkflow::relations()), '/coordinator/travel'),
        ], 201);
    }

    public function scheduleBatch(Request $request)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:50'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'travel_date' => ['required', 'date_format:Y-m-d'],
        ]);
        $travelDate = \Illuminate\Support\Carbon::parse($data['travel_date'], config('app.timezone'))->startOfDay();
        if ($travelDate->lt(now()->startOfDay())) {
            throw ValidationException::withMessages(['travel_date' => 'Travel date cannot be in the past.']);
        }

        $students = collect($data['student_ids'])->map(function (int $studentId) use ($request, $travelDate): Student {
            $student = ProgramAccess::studentQuery($request->user())
                ->with(['hte', 'program.college'])->findOrFail($studentId);
            $this->ensureSchedulable($request, $student, $travelDate->toDateString());

            return $student;
        });

        $sessions = DB::transaction(function () use ($request, $students, $travelDate) {
            $created = collect();
            foreach ($students as $student) {
                $existing = TravelLog::query()
                    ->whereHas('participants', fn ($query) => $query->whereKey($student->id))
                    ->whereIn('status', ['scheduled', 'active', 'traveling', 'arrived'])
                    ->lockForUpdate()->exists();
                if ($existing) {
                    throw ValidationException::withMessages([
                        'student_ids' => $student->full_name.' already has an upcoming or active travel session.',
                    ]);
                }

                $session = TravelLog::create([
                    'created_by' => $request->user()->id,
                    'coordinator_id' => $request->user()->id,
                    'hte_id' => $student->hte_id,
                    'session_code' => 'TRV-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                    'destination' => $student->hte->address,
                    'scheduled_at' => $travelDate,
                    'status' => 'scheduled',
                ]);
                $session->participants()->attach($student->id);
                SystemNotification::sendToUser(
                    $student->user_id,
                    'Upcoming Travel Schedule',
                    'You have been scheduled for travel on '.$travelDate->format('F j, Y').'. Please check Travel Monitoring for more details.',
                    'travel',
                    '/student/travel',
                );
                $created->push($session);
            }

            return $created;
        });

        return response()->json([
            'message' => $sessions->count().' students have been scheduled for travel on '.$travelDate->format('F j, Y').'. Students have been notified.',
            'count' => $sessions->count(),
            'travel_date' => $travelDate->toDateString(),
        ], 201);
    }

    public function reschedule(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeCoordinator($request->user(), $travel);
        $data = $request->validate([
            'travel_date' => ['required_without:scheduled_at', 'date_format:Y-m-d'],
            'scheduled_at' => ['required_without:travel_date', 'date', 'after:now'],
        ]);
        $scheduledAt = isset($data['travel_date'])
            ? \Illuminate\Support\Carbon::parse($data['travel_date'], config('app.timezone'))->startOfDay()
            : \Illuminate\Support\Carbon::parse($data['scheduled_at']);
        if (isset($data['travel_date']) && $scheduledAt->lt(now()->startOfDay())) {
            throw ValidationException::withMessages(['travel_date' => 'Travel date cannot be in the past.']);
        }
        $student = $travel->participants()->firstOrFail();
        $this->ensureSchedulable($request, $student->load('hte'), $scheduledAt->toDateString());
        $old = $travel->scheduled_at?->format('M j, Y');
        $session = DB::transaction(function () use ($travel, $scheduledAt) {
            $locked = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
            abort_unless($locked->status === 'scheduled', 422, 'Only an upcoming travel session can be rescheduled.');
            $locked->update(['scheduled_at' => $scheduledAt]);
            return $locked;
        });

        SystemNotification::sendToUser($student->user_id, 'Travel schedule changed',
            'Your travel schedule changed from '.$old.' to '.$session->scheduled_at->format('M j, Y').'.',
            'travel', '/student/travel');

        return response()->json([
            'message' => 'Travel rescheduled and the student was notified.',
            'session' => TravelWorkflow::sessionPayload($session->load(TravelWorkflow::relations()), '/coordinator/travel'),
        ]);
    }

    public function cancelSchedule(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeCoordinator($request->user(), $travel);
        $student = $travel->participants()->firstOrFail();
        $session = DB::transaction(function () use ($travel) {
            $locked = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
            abort_unless($locked->status === 'scheduled', 422, 'Only an upcoming travel session can be cancelled.');
            $locked->update(['status' => 'cancelled']);
            return $locked;
        });
        SystemNotification::sendToUser($student->user_id, 'Travel schedule cancelled',
            'Your travel scheduled for '.$session->scheduled_at?->format('M j, Y').' was cancelled.',
            'travel', '/student/travel');

        return response()->json(['message' => 'Travel schedule cancelled and the student was notified.']);
    }

    private function ensureSchedulable(Request $request, Student $student, string $scheduledAt): void
    {
        $student->loadMissing('hte');
        if (! in_array($student->internship_status, ['pending', 'active'], true)
            || $student->schedule_status !== 'approved' || ! $student->ojt_start_date || ! $student->hte?->is_active) {
            throw ValidationException::withMessages(['student_id' => 'A pending or active internship, approved deployment, OJT start date, and active HTE assignment are required.']);
        }
        if ((int) TravelWorkflow::coordinatorFor($student)?->id !== (int) $request->user()->id) {
            abort(404);
        }
        $date = \Illuminate\Support\Carbon::parse($scheduledAt)->setTimezone(config('app.timezone'))->startOfDay();
        // Travel to the HTE may precede the OJT start date.
        if (! OjtCompletion::usesAutomaticEnd($student) && $student->ojt_end_date && $date->gt($student->ojt_end_date)) {
            throw ValidationException::withMessages(['scheduled_at' => 'Travel date cannot be after the student internship end date.']);
        }
    }

    public function show(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeCoordinator($request->user(), $travel);

        return response()->json([
            'session' => TravelWorkflow::sessionPayload($travel->load(TravelWorkflow::relations()), '/coordinator/travel'),
        ]);
    }

    public function generateReport(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeCoordinator($request->user(), $travel);
        abort_unless($travel->status === 'completed', 422, 'A report can only be generated after the Student completes the travel session.');

        $report = DB::transaction(function () use ($request, $travel) {
            $travel = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);

            return TravelReport::firstOrCreate(
                ['travel_log_id' => $travel->id],
                [
                    'coordinator_id' => $request->user()->id,
                    'report_number' => 'TMR-'.$travel->session_code,
                    'status' => 'draft',
                    'version' => 1,
                    'generated_at' => now(),
                ],
            );
        });

        return response()->json([
            'message' => $report->wasRecentlyCreated ? 'Travel report generated from the saved Student evidence.' : 'The existing travel report was loaded.',
            'session' => TravelWorkflow::sessionPayload($travel->fresh()->load(TravelWorkflow::relations()), '/coordinator/travel'),
        ], $report->wasRecentlyCreated ? 201 : 200);
    }

    public function updateReport(Request $request, TravelReport $report)
    {
        $report->loadMissing('travelLog');
        TravelWorkflow::authorizeCoordinator($request->user(), $report->travelLog);
        abort_unless(in_array($report->status, TravelReport::EDITABLE_STATUSES, true), 422, 'This report is locked after submission.');
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:5000']]);
        $report->update(['remarks' => $data['remarks'] ?? null]);

        return response()->json([
            'message' => 'Coordinator remarks saved.',
            'report' => TravelWorkflow::reportPayload($report->fresh()),
        ]);
    }

    public function submitReport(Request $request, TravelReport $report)
    {
        $report->loadMissing('travelLog.participants');
        TravelWorkflow::authorizeCoordinator($request->user(), $report->travelLog);
        abort_unless(in_array($report->status, TravelReport::EDITABLE_STATUSES, true), 422, 'Only a draft report can be submitted.');

        DB::transaction(function () use ($request, $report) {
            $locked = TravelReport::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless(in_array($locked->status, TravelReport::EDITABLE_STATUSES, true), 422, 'This report is no longer editable.');
            $locked->update([
                'status' => 'pending_program_head',
                'submitted_at' => now(),
                'program_head_validated_at' => null,
                'forwarded_at' => null,
                'final_validated_at' => null,
            ]);
            $locked->validations()->create([
                'reviewer_id' => $request->user()->id,
                'reviewer_role' => 'coordinator',
                'action' => 'submitted',
                'reviewed_at' => now(),
                'report_version' => $locked->version,
            ]);
        });

        $student = $report->travelLog->participants->first();
        User::query()->forRole('program_head')->where('is_active', true)
            ->forAssignedProgram((int) $student?->program_id)
            ->pluck('id')->each(fn (int $userId) => SystemNotification::sendToUser(
                $userId,
                'New travel report submitted',
                $report->report_number.' is ready for Program Head validation.',
                'travel',
                '/program-head/travel',
            ));

        return response()->json([
            'message' => 'Report submitted to the Program Head.',
            'report' => TravelWorkflow::reportPayload($report->fresh()),
        ]);
    }

    public function arrivalPhoto(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeCoordinator($request->user(), $travel);
        $path = $this->privateStoragePath($travel->arrival_photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Arrival photo not found.');

        return response()->file($this->privateDisk()->path($path));
    }

    public function checkpointPhoto(Request $request, TravelLog $travel, TravelCheckpoint $checkpoint)
    {
        TravelWorkflow::authorizeCoordinator($request->user(), $travel);
        abort_unless((int) $checkpoint->travel_log_id === (int) $travel->id, 404);
        $path = $this->privateStoragePath($checkpoint->photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Checkpoint photo not found.');

        return response()->file($this->privateDisk()->path($path));
    }

    private function sessions(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());

        return TravelLog::query()
            ->where('coordinator_id', $request->user()->id)
            ->whereHas('participants', fn ($query) => $query->whereIn('students.id', ProgramAccess::studentQuery($request->user())->select('students.id')));
    }
}
