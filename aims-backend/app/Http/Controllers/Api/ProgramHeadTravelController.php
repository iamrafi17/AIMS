<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProgramHeadTravelController extends Controller
{
    public function index()
    {
        $sessions = TravelLog::with([
            'student.user',
            'student.program',
            'student.college',
            'student.hte',
            'checkpoints.verifier',
        ])
            ->latest('start_time')
            ->get();

        $checkpoints = $sessions->flatMap->checkpoints;
        $monthSessions = $sessions->filter(fn (TravelLog $session) => $session->start_time?->isSameMonth(now()));

        return response()->json([
            'summary' => [
                'total_sessions' => $sessions->count(),
                'active_sessions' => $sessions->where('status', 'active')->count(),
                'completed_this_month' => $monthSessions->where('status', 'completed')->count(),
                'participants' => $sessions->pluck('student_id')->unique()->count(),
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

    public function photo(TravelCheckpoint $checkpoint)
    {
        if (! $checkpoint->photo_path || ! Storage::disk('public')->exists($checkpoint->photo_path)) {
            return response()->json(['message' => 'Verification photo not found.'], 404);
        }

        return response()->file(Storage::disk('public')->path($checkpoint->photo_path));
    }

    private function sessionPayload(TravelLog $session): array
    {
        $checkpoints = $session->checkpoints
            ->sortBy('created_at')
            ->map(fn (TravelCheckpoint $checkpoint) => $this->checkpointPayload($checkpoint))
            ->values();
        $verified = $checkpoints->where('is_verified', true)->count();
        $total = $checkpoints->count();

        return [
            'id' => $session->id,
            'session_code' => $session->session_code,
            'status' => $session->status,
            'start_time' => $session->start_time?->toIso8601String(),
            'end_time' => $session->end_time?->toIso8601String(),
            'duration_minutes' => $session->start_time
                ? (int) $session->start_time->diffInMinutes($session->end_time ?? now())
                : 0,
            'participant_count' => $session->student ? 1 : 0,
            'participants' => $session->student ? [[
                'id' => $session->student->id,
                'name' => trim($session->student->first_name.' '.$session->student->last_name),
                'student_id' => $session->student->student_id,
                'program' => $session->student->program?->code,
                'college' => $session->student->college?->code,
                'hte' => $session->student->hte?->name ?? 'Not deployed',
                'contact' => $session->student->phone,
                'role' => 'Lead traveler',
            ]] : [],
            'student' => $session->student ? [
                'id' => $session->student->id,
                'name' => trim($session->student->first_name.' '.$session->student->last_name),
                'student_id' => $session->student->student_id,
                'program' => $session->student->program?->code,
                'college' => $session->student->college?->code,
                'hte' => $session->student->hte?->name ?? 'Not deployed',
            ] : null,
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
        ];
    }

    private function checkpointPayload(TravelCheckpoint $checkpoint): array
    {
        return [
            'id' => $checkpoint->id,
            'travel_log_id' => $checkpoint->travel_log_id,
            'checkpoint_name' => $checkpoint->checkpoint_name,
            'latitude' => (float) $checkpoint->latitude,
            'longitude' => (float) $checkpoint->longitude,
            'photo_path' => $checkpoint->photo_path,
            'photo_url' => $checkpoint->photo_path ? url(Storage::disk('public')->url($checkpoint->photo_path)) : null,
            'notes' => $checkpoint->notes,
            'is_verified' => (bool) $checkpoint->is_verified,
            'verified_by' => $checkpoint->verifier?->name,
            'verified_at' => $checkpoint->verified_at?->toIso8601String(),
            'recorded_at' => $checkpoint->created_at?->toIso8601String(),
        ];
    }
}
