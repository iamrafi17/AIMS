<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;

class ProgramHeadTravelController extends Controller
{
    public function index(Request $request)
    {
        $sessionsQuery = TravelLog::with([
            'creator:id,name',
            'hte:id,program_id,name,address',
            'participants.user',
            'participants.program',
            'participants.college',
            'participants.hte',
            'checkpoints.verifier',
        ]);

        if ($request->user()->role === 'program_head') {
            $programId = ProgramAccess::programId($request->user());
            $sessionsQuery->whereHas('participants', fn ($query) => $query->where('program_id', $programId));
        }

        $sessions = $sessionsQuery
            ->latest('start_time')
            ->get();

        $checkpoints = $sessions->flatMap->checkpoints;
        $monthSessions = $sessions->filter(fn (TravelLog $session) => $session->start_time?->isSameMonth(now()));

        return response()->json([
            'summary' => [
                'total_sessions' => $sessions->count(),
                'active_sessions' => $sessions->where('status', 'active')->count(),
                'completed_this_month' => $monthSessions->where('status', 'completed')->count(),
                'participants' => $sessions->flatMap->participants->pluck('id')->unique()->count(),
                'total_checkpoints' => $checkpoints->count(),
                'pending_checkpoints' => $checkpoints->where('is_verified', false)->count(),
                'photos_for_review' => $checkpoints->whereNotNull('photo_path')->where('is_verified', false)->count(),
                'verified_checkpoints' => $checkpoints->where('is_verified', true)->count(),
            ],
            'sessions' => $sessions->map(fn (TravelLog $session) => $this->sessionPayload($session))->values(),
        ]);
    }

    public function verifyCheckpoint(Request $request, TravelCheckpoint $checkpoint)
    {
        if ($request->user()->role === 'program_head') {
            ProgramAccess::authorizeCheckpoint($request->user(), $checkpoint);
        }
        $validated = $request->validate([
            'verified' => ['sometimes', 'boolean'],
        ]);
        $verified = $validated['verified'] ?? true;

        $checkpoint->update([
            'is_verified' => $verified,
            'verified_by' => $verified ? $request->user()->id : null,
            'verified_at' => $verified ? now() : null,
        ]);

        return response()->json([
            'message' => $verified ? 'Checkpoint evidence verified.' : 'Checkpoint verification removed.',
            'checkpoint' => $this->checkpointPayload($checkpoint->fresh('verifier')),
        ]);
    }

    public function photo(Request $request, TravelCheckpoint $checkpoint)
    {
        if ($request->user()->role === 'program_head') {
            ProgramAccess::authorizeCheckpoint($request->user(), $checkpoint);
        }
        $path = $this->privateStoragePath($checkpoint->photo_path);
        if (! $path || ! $this->privateDisk()->exists($path)) {
            return response()->json(['message' => 'Verification photo not found.'], 404);
        }

        return response()->file($this->privateDisk()->path($path));
    }

    public function arrivalPhoto(Request $request, TravelLog $travel)
    {
        if ($request->user()->role === 'program_head') {
            ProgramAccess::authorizeTravel($request->user(), $travel);
        }

        $path = $this->privateStoragePath($travel->arrival_photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Arrival photo not found.');

        return response()->file($this->privateDisk()->path($path));
    }

    private function sessionPayload(TravelLog $session): array
    {
        $checkpoints = $session->checkpoints
            ->sortBy('sequence')
            ->map(fn (TravelCheckpoint $checkpoint) => $this->checkpointPayload($checkpoint, $session))
            ->values();
        $verified = $checkpoints->where('is_verified', true)->count();
        $total = $checkpoints->count();
        $participants = $session->participants->map(fn ($student) => [
            'id' => $student->id,
            'name' => trim($student->first_name.' '.$student->last_name),
            'student_id' => $student->student_id,
            'program' => $student->program?->code,
            'college' => $student->college?->code,
            'hte' => $student->hte?->name ?? 'Not deployed',
            'contact' => $student->phone,
            'role' => 'Travel participant',
        ])->values();
        $leadParticipant = $participants->first();

        return [
            'id' => $session->id,
            'session_code' => $session->session_code,
            'status' => $session->status,
            'coordinator' => $session->creator?->name,
            'hte' => $session->hte ? [
                'id' => $session->hte->id,
                'name' => $session->hte->name,
                'address' => $session->hte->address,
            ] : null,
            'start_time' => $session->start_time?->toIso8601String(),
            'arrived_at' => $session->arrived_at?->toIso8601String(),
            'end_time' => $session->end_time?->toIso8601String(),
            'duration_minutes' => $session->start_time
                ? (int) $session->start_time->diffInMinutes($session->end_time ?? now())
                : 0,
            'participant_count' => $participants->count(),
            'participants' => $participants,
            'student' => $leadParticipant,
            'route' => [
                'origin' => $checkpoints->first()['checkpoint_name'] ?? 'No origin recorded',
                'destination' => $checkpoints->last()['checkpoint_name'] ?? 'No destination recorded',
                'stops' => $checkpoints->pluck('checkpoint_name')->values(),
                'progress' => $total > 0 ? round(($verified / $total) * 100, 1) : 0,
            ],
            'checkpoint_summary' => [
                'total' => $total,
                'verified' => $verified,
                'pending' => $total - $verified,
                'with_photos' => $checkpoints->whereNotNull('photo_path')->count(),
            ],
            'checkpoints' => $checkpoints,
            'has_arrival_photo' => (bool) $session->arrival_photo_path,
        ];
    }

    private function checkpointPayload(TravelCheckpoint $checkpoint, ?TravelLog $session = null): array
    {
        $photoPath = $this->privateStoragePath($checkpoint->photo_path);
        $session ??= $checkpoint->travelLog;
        $leadParticipant = $session?->participants?->first();

        return [
            'id' => $checkpoint->id,
            'travel_log_id' => $checkpoint->travel_log_id,
            'checkpoint_name' => $checkpoint->checkpoint_name,
            'sequence' => (int) $checkpoint->sequence,
            'point_type' => $checkpoint->point_type,
            'is_final_destination' => (bool) $checkpoint->is_final_destination,
            'latitude' => (float) $checkpoint->latitude,
            'longitude' => (float) $checkpoint->longitude,
            'readable_location' => $checkpoint->readable_location,
            'photo_path' => $photoPath ? 'protected' : null,
            'has_photo' => (bool) $photoPath,
            'notes' => $checkpoint->notes,
            'is_verified' => (bool) $checkpoint->is_verified,
            'verified_by' => $checkpoint->verifier?->name,
            'verified_at' => $checkpoint->verified_at?->toIso8601String(),
            'recorded_at' => $checkpoint->captured_at?->toIso8601String(),
            'session_code' => $session?->session_code,
            'student' => $leadParticipant ? [
                'id' => $leadParticipant->id,
                'name' => trim($leadParticipant->first_name.' '.$leadParticipant->last_name),
            ] : null,
        ];
    }
}
