<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\SystemNotification;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use App\Support\ReverseGeocoder;
use App\Support\DeploymentActivation;
use App\Support\OjtCompletion;
use App\Support\TravelWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentTravelController extends Controller
{
    public function index(Request $request)
    {
        DeploymentActivation::activateDue();
        $student = $request->user()->student;
        abort_unless($student, 404, 'Student profile not found.');
        $student->load(['program.college', 'hte']);

        $sessions = TravelLog::query()
            ->with(TravelWorkflow::relations())
            ->whereHas('participants', fn ($query) => $query->whereKey($student->id))
            ->latest('id')
            ->get();
        $current = $sessions->first(fn (TravelLog $session) => in_array($session->status, ['active', 'traveling', 'arrived'], true))
            ?? $sessions->first(fn (TravelLog $session) => $session->status === 'scheduled');

        $travelRequirement = ! $student->hte_id ? 'no_hte'
            : ($current?->status === 'scheduled' ? 'scheduled'
                : ($current ? 'in_progress' : 'awaiting_schedule'));

        return response()->json([
            'student' => TravelWorkflow::studentPayload($student),
            'coordinator' => ($coordinator = TravelWorkflow::coordinatorFor($student)) ? [
                'id' => $coordinator->id,
                'name' => $coordinator->name,
            ] : null,
            'can_start' => $this->startEligibility($request, $student, $current),
            'travel_requirement' => $travelRequirement,
            'current_session' => $current ? TravelWorkflow::sessionPayload($current, '/student/travel') : null,
            'history' => $sessions->reject(fn (TravelLog $session) => $current && $session->is($current))
                ->map(fn (TravelLog $session) => TravelWorkflow::sessionPayload($session, '/student/travel'))
                ->values(),
        ]);
    }

    public function start(Request $request)
    {
        DeploymentActivation::activateDue();
        $student = $request->user()->student;
        abort_unless($student, 404, 'Student profile not found.');
        $student->load(['program.college', 'hte']);
        $coordinates = $this->coordinates($request);

        if (! in_array($student->internship_status, ['pending', 'active'], true)) {
            throw ValidationException::withMessages(['session' => 'A pending or active internship is required before starting travel.']);
        }
        if ($student->schedule_status !== 'approved') {
            throw ValidationException::withMessages(['session' => 'Dean-approved deployment is required before starting travel.']);
        }
        if (! $student->hte_id || ! $student->hte?->is_active) {
            throw ValidationException::withMessages(['session' => 'An active assigned HTE is required before starting travel.']);
        }
        if (! $student->ojt_start_date) {
            throw ValidationException::withMessages(['session' => 'An OJT start date is required before starting travel.']);
        }
        if (! OjtCompletion::usesAutomaticEnd($student) && $student->ojt_end_date && now()->startOfDay()->gt($student->ojt_end_date)) {
            throw ValidationException::withMessages(['session' => 'Travel cannot start after your internship end date.']);
        }

        $session = DB::transaction(function () use ($request, $student, $coordinates) {
            $session = TravelLog::query()
                ->whereHas('participants', fn ($query) => $query->whereKey($student->id))
                ->where('status', 'scheduled')->latest('id')->lockForUpdate()->first();
            if (! $session) {
                throw ValidationException::withMessages(['session' => 'No travel schedule has been set by your coordinator.']);
            }
            if ((int) $session->hte_id !== (int) $student->hte_id) {
                throw ValidationException::withMessages(['session' => 'Your HTE assignment changed. Ask your coordinator to update the travel schedule.']);
            }
            if (now()->toDateString() !== $session->scheduled_at->toDateString()) {
                throw ValidationException::withMessages(['session' => 'Travel can start only on the scheduled date. Ask your coordinator to reschedule a missed trip.']);
            }
            $hasActiveSession = TravelLog::query()->whereKeyNot($session->id)
                ->whereHas('participants', fn ($query) => $query->whereKey($student->id))
                ->whereIn('status', ['active', 'traveling', 'arrived'])->exists();
            if ($hasActiveSession) {
                throw ValidationException::withMessages(['session' => 'Complete your current travel session before starting another one.']);
            }

            $capturedAt = now();
            $session->update(['start_time' => $capturedAt, 'departed_at' => $capturedAt, 'status' => 'traveling']);
            $session->checkpoints()->create([
                ...$coordinates,
                'sequence' => 1,
                'checkpoint_name' => 'Departure',
                'point_type' => 'departure',
                'captured_at' => $capturedAt,
                'captured_by' => $request->user()->id,
            ]);

            return $session;
        });

        SystemNotification::sendToUser($session->coordinator_id, 'Student travel departed', $student->full_name.' departed for '.$student->hte->name.'.', 'travel', '/coordinator/travel');
        SystemNotification::sendToUser($request->user()->id, 'Travel departure recorded', 'Your departure GPS location was saved successfully.', 'travel', '/student/travel');

        return response()->json([
            'message' => 'Departure recorded. Your travel session is now in progress.',
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
            $this->ensurePlausibleMovement($session, $coordinates);

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
            $this->ensurePlausibleMovement($session, $coordinates);
            $sequence = ((int) $session->checkpoints()->max('sequence')) + 1;

            $session->checkpoints()->create([
                ...$coordinates,
                'sequence' => $sequence,
                'checkpoint_name' => 'Checkpoint '.($session->checkpoints()->where('point_type', 'checkpoint')->count() + 1),
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
            $this->ensurePlausibleMovement($session, $coordinates);
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

    public function uploadCheckpointPhoto(Request $request, TravelLog $travel, TravelCheckpoint $checkpoint)
    {
        TravelWorkflow::authorizeStudent($request->user(), $travel);
        abort_unless((int) $checkpoint->travel_log_id === (int) $travel->id, 404);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);

        $newPath = null;
        try {
            $oldPath = DB::transaction(function () use ($request, $travel, $checkpoint, &$newPath) {
                $session = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
                abort_unless(in_array($session->status, ['active', 'traveling', 'arrived'], true), 422, 'Checkpoint photos are locked after the session ends.');
                $point = $session->checkpoints()->whereKey($checkpoint->id)->lockForUpdate()->firstOrFail();
                $newPath = $request->file('photo')->store('travel-checkpoints', 'local');
                $previous = $point->photo_path;
                $point->update(['photo_path' => $newPath]);

                return $previous;
            });
        } catch (\Throwable $error) {
            if ($newPath) {
                $this->privateDisk()->delete($newPath);
            }
            throw $error;
        }
        if ($oldPath) {
            $this->privateDisk()->delete($oldPath);
        }

        return response()->json([
            'message' => 'Checkpoint photo uploaded successfully.',
            'session' => TravelWorkflow::sessionPayload($travel->fresh()->load(TravelWorkflow::relations()), '/student/travel'),
        ]);
    }

    public function checkpointPhoto(Request $request, TravelLog $travel, TravelCheckpoint $checkpoint)
    {
        TravelWorkflow::authorizeStudent($request->user(), $travel);
        abort_unless((int) $checkpoint->travel_log_id === (int) $travel->id, 404);
        $path = $this->privateStoragePath($checkpoint->photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Checkpoint photo not found.');

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
            'accuracy' => ['required', 'numeric', 'min:0', 'max:500'],
            'position_timestamp' => ['required', 'integer', 'min:0'],
        ];
        if ($withRemarks) {
            $rules['remarks'] = ['nullable', 'string', 'max:1000'];
        }
        $data = $request->validate($rules);
        $positionAge = now()->getTimestampMs() - (int) $data['position_timestamp'];
        if ($positionAge > 120000 || $positionAge < -30000) {
            throw ValidationException::withMessages([
                'location' => ['The GPS reading is stale. Wait for a fresh location lock and try again.'],
            ]);
        }

        return [
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'gps_accuracy' => (float) $data['accuracy'],
            'notes' => $withRemarks ? ($data['remarks'] ?? null) : null,
            'readable_location' => ReverseGeocoder::describe((float) $data['latitude'], (float) $data['longitude']),
        ];
    }

    private function ensurePlausibleMovement(TravelLog $session, array $coordinates): void
    {
        $lastPoint = $session->checkpoints()->latest('sequence')->first();
        if (! $lastPoint) {
            return;
        }

        $distanceKm = $this->distanceKm(
            (float) $lastPoint->latitude,
            (float) $lastPoint->longitude,
            (float) $coordinates['latitude'],
            (float) $coordinates['longitude'],
        );
        $elapsedHours = max($lastPoint->captured_at?->diffInSeconds(now()) ?? 0, 0) / 3600;
        $accuracyBufferKm = (((float) ($lastPoint->gps_accuracy ?? 0)) + (float) $coordinates['gps_accuracy']) / 1000;
        $maximumPlausibleDistanceKm = 3 + ($elapsedHours * 160) + $accuracyBufferKm;

        if ($distanceKm > $maximumPlausibleDistanceKm) {
            throw ValidationException::withMessages([
                'location' => ['The GPS location jumped '.round($distanceKm, 1).' km from the previous point. Wait for the GPS to stabilize and try again.'],
            ]);
        }
    }

    private function distanceKm(float $firstLatitude, float $firstLongitude, float $secondLatitude, float $secondLongitude): float
    {
        $earthRadiusKm = 6371;
        $latitudeDelta = deg2rad($secondLatitude - $firstLatitude);
        $longitudeDelta = deg2rad($secondLongitude - $firstLongitude);
        $firstLatitudeRadians = deg2rad($firstLatitude);
        $secondLatitudeRadians = deg2rad($secondLatitude);
        $value = sin($latitudeDelta / 2) ** 2
            + cos($firstLatitudeRadians) * cos($secondLatitudeRadians) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($value), sqrt(1 - $value));
    }

    private function startEligibility(Request $request, Student $student, ?TravelLog $current): array
    {
        $reasons = [];
        if (! $request->user()->is_active) {
            $reasons[] = 'Student account is inactive.';
        }
        if (! in_array($student->internship_status, ['pending', 'active'], true)) {
            $reasons[] = 'A pending or active internship is required.';
        }
        if ($student->schedule_status !== 'approved') {
            $reasons[] = 'Dean-approved deployment is required.';
        }
        if (! $student->hte_id || ! $student->hte?->is_active) {
            $reasons[] = 'An active HTE assignment is required.';
        }
        if (! $student->ojt_start_date) {
            $reasons[] = 'An OJT start date is required.';
        }
        if (! OjtCompletion::usesAutomaticEnd($student) && $student->ojt_end_date && now()->startOfDay()->gt($student->ojt_end_date)) {
            $reasons[] = 'The internship period has ended.';
        }
        if ($current) {
            if ($current->status === 'scheduled') {
                if (now()->toDateString() !== $current->scheduled_at->toDateString()) {
                    $reasons[] = 'Travel can start only on the scheduled date.';
                }
            } else {
                $reasons[] = 'Another travel session is active.';
            }
        } else {
            $reasons[] = 'No travel schedule has been set by your coordinator.';
        }
        if (! TravelWorkflow::coordinatorFor($student)) {
            $reasons[] = 'No active Internship Coordinator is assigned.';
        }

        return ['allowed' => $reasons === [], 'reasons' => $reasons];
    }
}
