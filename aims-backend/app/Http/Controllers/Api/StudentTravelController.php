<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Models\TravelLog;
use App\Support\ReverseGeocoder;
use App\Support\TravelWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentTravelController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'Student profile not found.');
        $student->load(['program.college', 'hte']);

        $sessions = TravelLog::query()
            ->with(TravelWorkflow::relations())
            ->whereHas('participants', fn ($query) => $query->whereKey($student->id))
            ->latest('start_time')
            ->get();
        $current = $sessions->first(fn (TravelLog $session) => in_array($session->status, ['active', 'traveling', 'arrived'], true));

        return response()->json([
            'student' => TravelWorkflow::studentPayload($student),
            'coordinator' => ($coordinator = TravelWorkflow::coordinatorFor($student)) ? [
                'id' => $coordinator->id,
                'name' => $coordinator->name,
            ] : null,
            'can_start' => $this->startEligibility($request, $student, $current),
            'current_session' => $current ? TravelWorkflow::sessionPayload($current, '/student/travel') : null,
            'history' => $sessions->reject(fn (TravelLog $session) => $current && $session->is($current))
                ->map(fn (TravelLog $session) => TravelWorkflow::sessionPayload($session, '/student/travel'))
                ->values(),
        ]);
    }

    public function start(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'Student profile not found.');
        $student->load(['program.college', 'hte']);
        $coordinates = $this->coordinates($request);

        if ($student->registration_status !== 'approved' || $student->internship_status !== 'active') {
            throw ValidationException::withMessages(['session' => 'Your approved active internship is required before starting travel.']);
        }
        if (! $student->hte_id || ! $student->hte?->is_active) {
            throw ValidationException::withMessages(['session' => 'An active assigned HTE is required before starting travel.']);
        }
        if (($student->ojt_start_date && now()->startOfDay()->lt($student->ojt_start_date))
            || ($student->ojt_end_date && now()->startOfDay()->gt($student->ojt_end_date))) {
            throw ValidationException::withMessages(['session' => 'Travel can only start during your active internship period.']);
        }

        $coordinator = TravelWorkflow::coordinatorFor($student);
        if (! $coordinator) {
            throw ValidationException::withMessages(['session' => 'No active Internship Coordinator is assigned to your program.']);
        }

        $session = DB::transaction(function () use ($request, $student, $coordinator, $coordinates) {
            $hasActiveSession = TravelLog::query()
                ->whereHas('participants', fn ($query) => $query->whereKey($student->id))
                ->whereIn('status', ['active', 'traveling', 'arrived'])
                ->lockForUpdate()
                ->exists();
            if ($hasActiveSession) {
                throw ValidationException::withMessages(['session' => 'Complete your current travel session before starting another one.']);
            }

            $session = TravelLog::create([
                'created_by' => $request->user()->id,
                'coordinator_id' => $coordinator->id,
                'hte_id' => $student->hte_id,
                'session_code' => 'TRV-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'destination' => $student->hte->address,
                'start_time' => now(),
                'status' => 'active',
            ]);
            $session->participants()->attach($student->id);
            $session->checkpoints()->create([
                ...$coordinates,
                'sequence' => 1,
                'checkpoint_name' => 'Starting Location',
                'point_type' => 'start',
                'captured_at' => now(),
                'captured_by' => $request->user()->id,
            ]);

            return $session;
        });

        SystemNotification::sendToUser($coordinator->id, 'Student travel session started', $student->full_name.' started travel to '.$student->hte->name.'.', 'travel', '/coordinator/travel');
        SystemNotification::sendToUser($request->user()->id, 'Travel session started', 'Your starting GPS location was saved successfully.', 'travel', '/student/travel');

        return response()->json([
            'message' => 'Travel session started and your starting location was captured.',
            'session' => TravelWorkflow::sessionPayload($session->load(TravelWorkflow::relations()), '/student/travel'),
        ], 201);
    }

    public function departure(Request $request, TravelLog $travel)
    {
        $coordinates = $this->coordinates($request);
        $session = DB::transaction(function () use ($request, $travel, $coordinates) {
            $session = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
            TravelWorkflow::authorizeStudent($request->user(), $session);
            abort_unless($session->status === 'active', 422, 'Departure can only start after the starting location is captured.');
            abort_unless($session->checkpoints()->count() === 1, 422, 'The departure event has already been recorded.');

            $capturedAt = now();
            $session->checkpoints()->create([
                ...$coordinates,
                'sequence' => 2,
                'checkpoint_name' => 'Departure',
                'point_type' => 'departure',
                'captured_at' => $capturedAt,
                'captured_by' => $request->user()->id,
            ]);
            $session->update(['status' => 'traveling', 'departed_at' => $capturedAt]);

            return $session;
        });

        return response()->json([
            'message' => 'Departure recorded. Your travel session is now in progress.',
            'session' => TravelWorkflow::sessionPayload($session->fresh()->load(TravelWorkflow::relations()), '/student/travel'),
        ]);
    }

    public function checkpoint(Request $request, TravelLog $travel)
    {
        $coordinates = $this->coordinates($request, true);
        $session = DB::transaction(function () use ($request, $travel, $coordinates) {
            $session = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
            TravelWorkflow::authorizeStudent($request->user(), $session);
            abort_unless($session->status === 'traveling', 422, 'Checkpoints can only be added while the session is traveling.');
            $sequence = ((int) $session->checkpoints()->max('sequence')) + 1;

            $session->checkpoints()->create([
                ...$coordinates,
                'sequence' => $sequence,
                'checkpoint_name' => 'Checkpoint '.($sequence - 2),
                'point_type' => 'checkpoint',
                'captured_at' => now(),
                'captured_by' => $request->user()->id,
            ]);

            return $session;
        });

        return response()->json([
            'message' => 'Checkpoint saved successfully.',
            'session' => TravelWorkflow::sessionPayload($session->fresh()->load(TravelWorkflow::relations()), '/student/travel'),
        ], 201);
    }

    public function arrival(Request $request, TravelLog $travel)
    {
        $coordinates = $this->coordinates($request);
        $session = DB::transaction(function () use ($request, $travel, $coordinates) {
            $session = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
            TravelWorkflow::authorizeStudent($request->user(), $session);
            abort_unless($session->status === 'traveling', 422, 'Final arrival can only be marked after departure.');
            $capturedAt = now();
            $sequence = ((int) $session->checkpoints()->max('sequence')) + 1;

            $session->checkpoints()->create([
                ...$coordinates,
                'sequence' => $sequence,
                'checkpoint_name' => 'Final Arrival',
                'point_type' => 'final_arrival',
                'is_final_destination' => true,
                'captured_at' => $capturedAt,
                'captured_by' => $request->user()->id,
            ]);
            $session->update(['status' => 'arrived', 'arrived_at' => $capturedAt]);

            return $session;
        });

        return response()->json([
            'message' => 'Final arrival recorded. Upload your arrival photo before ending the session.',
            'session' => TravelWorkflow::sessionPayload($session->fresh()->load(TravelWorkflow::relations()), '/student/travel'),
        ]);
    }

    public function uploadArrivalPhoto(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeStudent($request->user(), $travel);
        abort_unless($travel->status === 'arrived', 422, 'Mark final arrival before uploading the arrival photo.');
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);

        $path = $request->file('photo')->store('travel-arrivals', 'local');
        $oldPath = $travel->arrival_photo_path;
        $travel->update(['arrival_photo_path' => $path]);
        if ($oldPath) {
            $this->privateDisk()->delete($oldPath);
        }

        return response()->json([
            'message' => 'Arrival photo uploaded successfully.',
            'session' => TravelWorkflow::sessionPayload($travel->fresh()->load(TravelWorkflow::relations()), '/student/travel'),
        ]);
    }

    public function arrivalPhoto(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeStudent($request->user(), $travel);
        $path = $this->privateStoragePath($travel->arrival_photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Arrival photo not found.');

        return response()->file($this->privateDisk()->path($path));
    }

    public function end(Request $request, TravelLog $travel)
    {
        $session = DB::transaction(function () use ($request, $travel) {
            $session = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
            TravelWorkflow::authorizeStudent($request->user(), $session);
            abort_unless($session->status === 'arrived', 422, 'Record final arrival before ending the session.');
            abort_unless($session->arrival_photo_path && $this->privateDisk()->exists($session->arrival_photo_path), 422, 'Upload the arrival photo before ending the session.');
            $session->update(['status' => 'completed', 'end_time' => now()]);

            return $session;
        });

        SystemNotification::sendToUser($session->coordinator_id, 'Student travel session completed', $request->user()->name.' completed travel session '.$session->session_code.'.', 'travel', '/coordinator/travel');
        SystemNotification::sendToUser($request->user()->id, 'Travel session completed', 'Your travel evidence is now locked for Coordinator review.', 'travel', '/student/travel');

        return response()->json([
            'message' => 'Travel session completed successfully. Your GPS records are now read-only.',
            'session' => TravelWorkflow::sessionPayload($session->load(TravelWorkflow::relations()), '/student/travel'),
        ]);
    }

    private function coordinates(Request $request, bool $withRemarks = false): array
    {
        $rules = [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
        if ($withRemarks) {
            $rules['remarks'] = ['nullable', 'string', 'max:1000'];
        }
        $data = $request->validate($rules);

        return [
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'gps_accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            'notes' => $withRemarks ? ($data['remarks'] ?? null) : null,
            'readable_location' => ReverseGeocoder::describe((float) $data['latitude'], (float) $data['longitude']),
        ];
    }

    private function startEligibility(Request $request, $student, ?TravelLog $current): array
    {
        $reasons = [];
        if (! $request->user()->is_active) $reasons[] = 'Student account is inactive.';
        if ($student->registration_status !== 'approved' || $student->internship_status !== 'active') $reasons[] = 'An approved active internship is required.';
        if (! $student->hte_id || ! $student->hte?->is_active) $reasons[] = 'An active HTE assignment is required.';
        if (($student->ojt_start_date && now()->startOfDay()->lt($student->ojt_start_date)) || ($student->ojt_end_date && now()->startOfDay()->gt($student->ojt_end_date))) $reasons[] = 'The internship period is not currently active.';
        if ($current) $reasons[] = 'Another travel session is active.';
        if (! TravelWorkflow::coordinatorFor($student)) $reasons[] = 'No active Internship Coordinator is assigned.';

        return ['allowed' => $reasons === [], 'reasons' => $reasons];
    }
}
