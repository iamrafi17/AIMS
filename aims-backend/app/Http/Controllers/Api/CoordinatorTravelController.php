<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\SystemNotification;
use App\Models\TravelLog;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoordinatorTravelController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $sessions = TravelLog::with([
            'student.program',
            'student.hte',
            'creator:id,name',
            'companions',
            'checkpoints' => fn ($query) => $query->orderBy('sequence'),
            'checkpoints.verifier:id,name',
        ])->whereHas('student', fn ($query) => $query->where('program_id', $programId))
            ->latest('scheduled_at')->get();

        return response()->json([
            'sessions' => $sessions,
            'students' => Student::with(['program:id,code', 'hte:id,name'])
                ->where('program_id', $programId)
                ->where('registration_status', 'approved')
                ->orderBy('last_name')
                ->get(['id', 'student_id', 'first_name', 'middle_name', 'last_name', 'program_id', 'hte_id']),
            'summary' => [
                'total' => $sessions->count(),
                'scheduled' => $sessions->where('status', 'scheduled')->count(),
                'active' => $sessions->where('status', 'active')->count(),
                'completed' => $sessions->where('status', 'completed')->count(),
                'pending_evidence' => $sessions->flatMap->checkpoints->where('is_verified', false)->whereNotNull('photo_path')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->sessionData($request);
        $session = DB::transaction(function () use ($data, $request) {
            $session = TravelLog::create([
                'student_id' => $data['student_id'],
                'created_by' => $request->user()->id,
                'session_code' => 'TRV-'.now()->format('ymd').'-'.str_pad((string) (TravelLog::max('id') + 1), 3, '0', STR_PAD_LEFT),
                'destination' => $data['destination'],
                'purpose' => $data['purpose'],
                'route_notes' => $data['route_notes'] ?? null,
                'scheduled_at' => $data['scheduled_at'],
                'status' => 'scheduled',
            ]);
            $this->syncChildren($session, $data);

            return $session;
        });
        SystemNotification::sendToUser(
            $session->student?->user_id,
            'New travel session assigned',
            $session->session_code.' to '.$session->destination.' was created by your coordinator.',
            'travel',
            '/student/travel',
        );

        return response()->json(['message' => 'Travel session created and assigned to the student.', 'session' => $session->load(['student', 'companions', 'checkpoints'])], 201);
    }

    public function update(Request $request, TravelLog $travel)
    {
        ProgramAccess::authorizeTravel($request->user(), $travel);
        abort_if($travel->status === 'completed', 422, 'Completed travel sessions cannot be edited.');
        $data = $this->sessionData($request);
        DB::transaction(function () use ($travel, $data) {
            $travel->update(collect($data)->only(['student_id', 'destination', 'purpose', 'route_notes', 'scheduled_at'])->all());
            $this->syncChildren($travel, $data);
        });

        return response()->json(['message' => 'Travel session updated successfully.']);
    }

    public function destroy(Request $request, TravelLog $travel)
    {
        ProgramAccess::authorizeTravel($request->user(), $travel);
        abort_if($travel->status === 'active', 422, 'End or cancel the active session before deleting it.');
        $travel->delete();

        return response()->json(['message' => 'Travel session deleted.']);
    }

    public function cancel(Request $request, TravelLog $travel)
    {
        ProgramAccess::authorizeTravel($request->user(), $travel);
        abort_if($travel->status === 'completed', 422, 'Completed sessions cannot be cancelled.');
        $travel->update(['status' => 'cancelled', 'end_time' => now()]);

        return response()->json(['message' => 'Travel session cancelled.']);
    }

    private function sessionData(Request $request): array
    {
        $programId = ProgramAccess::programId($request->user());

        return $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->where('program_id', $programId)],
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:3000'],
            'route_notes' => ['nullable', 'string', 'max:3000'],
            'scheduled_at' => ['required', 'date'],
            'companions' => ['nullable', 'array', 'max:20'],
            'companions.*.name' => ['required', 'string', 'max:255'],
            'companions.*.type' => ['required', Rule::in(['student', 'faculty', 'staff', 'other'])],
            'companions.*.contact' => ['nullable', 'string', 'max:40'],
            'companions.*.relationship' => ['nullable', 'string', 'max:100'],
            'checkpoints' => ['required', 'array', 'min:2', 'max:20'],
            'checkpoints.*.checkpoint_name' => ['required', 'string', 'max:255'],
            'checkpoints.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'checkpoints.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'checkpoints.*.expected_at' => ['nullable', 'date'],
        ]);
    }

    private function syncChildren(TravelLog $travel, array $data): void
    {
        $travel->companions()->delete();
        $travel->companions()->createMany($data['companions'] ?? []);

        $existingEvidence = $travel->checkpoints()->whereNotNull('photo_path')->exists();
        if (! $existingEvidence) {
            $travel->checkpoints()->delete();
            $travel->checkpoints()->createMany(collect($data['checkpoints'])->values()->map(fn ($checkpoint, $index) => [
                ...$checkpoint,
                'sequence' => $index + 1,
            ])->all());
        }
    }
}
