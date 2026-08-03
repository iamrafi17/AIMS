<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StudentTravelController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'Student profile not found.');

        return response()->json(TravelLog::with([
            'creator:id,name',
            'companions',
            'checkpoints' => fn ($query) => $query->orderBy('sequence'),
        ])->where('student_id', $student->id)->latest('scheduled_at')->get());
    }

    public function start(Request $request, TravelLog $travel)
    {
        $this->owns($request, $travel);
        if ($travel->status !== 'scheduled') {
            throw ValidationException::withMessages(['travel' => 'Only scheduled travel can be started.']);
        }
        $request->validate(['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);
        $travel->update(['status' => 'active', 'start_time' => now()]);

        return response()->json(['message' => 'Travel session started using server time.', 'server_time' => now()->toIso8601String()]);
    }

    public function checkpoint(Request $request, TravelCheckpoint $checkpoint)
    {
        $checkpoint->load('travelLog');
        $this->owns($request, $checkpoint->travelLog);
        abort_unless($checkpoint->travelLog->status === 'active', 422, 'The travel session is not active.');
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:8192'],
        ]);
        if ($request->hasFile('photo')) {
            if ($checkpoint->photo_path) {
                Storage::disk('public')->delete($checkpoint->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('travel-checkpoints', 'public');
        }
        $checkpoint->update([...$data, 'is_verified' => false, 'verified_by' => null, 'verified_at' => null]);

        return response()->json(['message' => 'Checkpoint evidence submitted for verification.']);
    }

    public function end(Request $request, TravelLog $travel)
    {
        $this->owns($request, $travel);
        abort_unless($travel->status === 'active', 422, 'Only active travel can be completed.');
        $travel->update(['status' => 'completed', 'end_time' => now()]);

        return response()->json(['message' => 'Travel session completed using server time.']);
    }

    private function owns(Request $request, TravelLog $travel): void
    {
        abort_unless($request->user()->student?->id === $travel->student_id, 403);
    }
}
