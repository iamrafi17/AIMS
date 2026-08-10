<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HTE;
use App\Models\Student;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use App\Support\ProgramAccess;
use App\Support\ReverseGeocoder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CoordinatorTravelController extends Controller
{
    public function index(Request $request)
    {
        ProgramAccess::programId($request->user());
        $sessions = $this->coordinatorSessions($request)
            ->with($this->sessionRelations())
            ->latest('start_time')
            ->get();

        return response()->json([
            'sessions' => $sessions->map(fn (TravelLog $session) => $this->sessionPayload($session))->values(),
            'summary' => [
                'total' => $sessions->count(),
                'active' => $sessions->where('status', 'active')->count(),
                'final_arrivals' => $sessions->whereNotNull('arrived_at')->count(),
                'completed' => $sessions->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function setup(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());

        $htes = HTE::query()
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->withCount(['students as assigned_students_count' => fn ($query) => $query
                ->where('program_id', $programId)
                ->where('registration_status', 'approved')])
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'latitude', 'longitude']);

        return response()->json(['htes' => $htes]);
    }

    public function studentsForHte(Request $request, HTE $hte)
    {
        $programId = ProgramAccess::programId($request->user());
        ProgramAccess::authorizeHte($request->user(), $hte);

        $students = Student::query()
            ->with(['program.college', 'hte:id,name'])
            ->where('program_id', $programId)
            ->where('hte_id', $hte->id)
            ->where('registration_status', 'approved')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'hte' => $this->htePayload($hte),
            'students' => $students->map(fn (Student $student) => $this->studentPayload($student))->values(),
        ]);
    }

    public function active(Request $request)
    {
        ProgramAccess::programId($request->user());
        $session = $this->coordinatorSessions($request)
            ->where('status', 'active')
            ->with($this->sessionRelations())
            ->latest('start_time')
            ->first();

        return response()->json([
            'session' => $session ? $this->sessionPayload($session) : null,
        ]);
    }

    public function show(Request $request, TravelLog $travel)
    {
        $this->authorizeCoordinatorSession($request, $travel);

        return response()->json([
            'session' => $this->sessionPayload($travel->load($this->sessionRelations())),
        ]);
    }

    public function startTravel(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $data = $request->validate([
            'hte_id' => ['required', 'integer', 'exists:htes,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $hte = HTE::findOrFail($data['hte_id']);
        ProgramAccess::authorizeHte($request->user(), $hte);
        abort_unless($hte->is_active, 422, 'The selected HTE is not active.');

        $students = Student::query()
            ->whereIn('id', $data['student_ids'])
            ->where('program_id', $programId)
            ->where('hte_id', $hte->id)
            ->where('registration_status', 'approved')
            ->get();

        if ($students->count() !== count($data['student_ids'])) {
            throw ValidationException::withMessages([
                'student_ids' => 'Every participant must be an approved student assigned to the selected HTE.',
            ]);
        }

        $startingPoint = [
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'readable_location' => ReverseGeocoder::describe(
                (float) $data['latitude'],
                (float) $data['longitude'],
            ),
        ];

        $session = DB::transaction(function () use ($request, $hte, $students, $startingPoint) {
            $hasUnfinishedSession = TravelLog::query()
                ->where('created_by', $request->user()->id)
                ->whereNotNull('hte_id')
                ->where('status', 'active')
                ->lockForUpdate()
                ->exists();

            if ($hasUnfinishedSession) {
                throw ValidationException::withMessages([
                    'session' => 'Complete the current travel session before starting another one.',
                ]);
            }

            $session = TravelLog::create([
                'created_by' => $request->user()->id,
                'hte_id' => $hte->id,
                'session_code' => 'TRV-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'start_time' => now(),
                'status' => 'active',
            ]);
            $session->participants()->attach($students->modelKeys());
            $session->checkpoints()->create([
                ...$startingPoint,
                'sequence' => 1,
                'checkpoint_name' => 'Starting Location',
                'point_type' => 'departure',
                'captured_at' => now(),
                'captured_by' => $request->user()->id,
            ]);

            return $session;
        });

        return response()->json([
            'message' => 'Travel session started and the starting departure location was captured.',
            'session' => $this->sessionPayload($session->load($this->sessionRelations())),
        ], 201);
    }

    public function departure(Request $request, TravelLog $travel)
    {
        $coordinates = $this->coordinates($request);
        $point = DB::transaction(function () use ($request, $travel, $coordinates) {
            $session = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
            $this->authorizeCoordinatorSession($request, $session);
            abort_unless($session->status === 'active', 422, 'Departure can only be captured for an active session.');
            $lastPoint = $session->checkpoints()->orderByDesc('sequence')->first();
            abort_unless($lastPoint?->point_type === 'arrival', 422, 'Mark arrival at the current location before starting the next departure.');
            abort_if($lastPoint->is_final_destination, 422, 'A new departure cannot be recorded after final arrival.');

            return $session->checkpoints()->create([
                ...$coordinates,
                'sequence' => $lastPoint->sequence + 1,
                'checkpoint_name' => 'Departure',
                'point_type' => 'departure',
                'captured_at' => now(),
                'captured_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Departure point captured.',
            'point' => $this->pointPayload($point),
        ], 201);
    }

    public function arrival(Request $request, TravelLog $travel)
    {
        $coordinates = $this->coordinates($request);
        $isFinalDestination = $request->boolean('is_final_destination');
        [$session, $point] = DB::transaction(function () use ($request, $travel, $coordinates, $isFinalDestination) {
            $session = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
            $this->authorizeCoordinatorSession($request, $session);
            abort_unless($session->status === 'active', 422, 'Arrival can only be marked for an active session.');
            $lastPoint = $session->checkpoints()->orderByDesc('sequence')->first();
            abort_unless($lastPoint?->point_type === 'departure', 422, 'Start departure before marking the next arrival.');

            $capturedAt = now();
            $point = $session->checkpoints()->create([
                ...$coordinates,
                'sequence' => $lastPoint->sequence + 1,
                'checkpoint_name' => $isFinalDestination ? 'Final Arrival' : 'Arrival',
                'point_type' => 'arrival',
                'is_final_destination' => $isFinalDestination,
                'captured_at' => $capturedAt,
                'captured_by' => $request->user()->id,
            ]);
            if ($isFinalDestination) {
                $session->update(['arrived_at' => $capturedAt]);
            }

            return [$session, $point];
        });

        return response()->json([
            'message' => $isFinalDestination
                ? 'Final arrival recorded. Upload the required arrival photo to finish the session.'
                : 'Arrival recorded. Start departure again when the group leaves this location.',
            'point' => $this->pointPayload($point),
            'session' => $this->sessionPayload($session->fresh()->load($this->sessionRelations())),
        ], 201);
    }

    public function uploadArrivalPhoto(Request $request, TravelLog $travel)
    {
        $this->authorizeCoordinatorSession($request, $travel);
        abort_unless($travel->status === 'active', 422, 'Arrival evidence can only be uploaded for an active session.');
        abort_unless($travel->checkpoints()->where('is_final_destination', true)->exists(), 422, 'Record final arrival before uploading the arrival photo.');

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('photo')->store('travel-arrivals', 'local');
        $oldPath = $travel->arrival_photo_path;
        $travel->update(['arrival_photo_path' => $path]);
        if ($oldPath) {
            $this->privateDisk()->delete($oldPath);
        }

        return response()->json([
            'message' => 'Arrival photo uploaded successfully.',
            'session' => $this->sessionPayload($travel->fresh()->load($this->sessionRelations())),
        ]);
    }

    public function arrivalPhoto(Request $request, TravelLog $travel)
    {
        $this->authorizeCoordinatorSession($request, $travel);
        $path = $this->privateStoragePath($travel->arrival_photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Arrival photo not found.');

        return response()->file($this->privateDisk()->path($path));
    }

    public function endTravel(Request $request, TravelLog $travel)
    {
        $session = DB::transaction(function () use ($request, $travel) {
            $session = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);
            $this->authorizeCoordinatorSession($request, $session);
            abort_unless($session->status === 'active', 422, 'Only an active session can be completed.');
            abort_unless($session->checkpoints()->where('is_final_destination', true)->exists(), 422, 'The session has no final arrival point.');
            abort_unless($session->arrival_photo_path, 422, 'Upload the arrival photo before ending the session.');
            abort_unless($this->privateDisk()->exists($session->arrival_photo_path), 422, 'The uploaded arrival photo could not be found. Upload it again.');

            $session->update(['status' => 'completed', 'end_time' => now()]);

            return $session;
        });

        return response()->json([
            'message' => 'Travel session completed successfully.',
            'session' => $this->sessionPayload($session->load($this->sessionRelations())),
        ]);
    }

    public function cancel(Request $request, TravelLog $travel)
    {
        ProgramAccess::authorizeTravel($request->user(), $travel);
        abort_if($travel->status === 'completed', 422, 'Completed sessions cannot be cancelled.');
        $travel->update(['status' => 'cancelled', 'end_time' => now()]);

        return response()->json(['message' => 'Travel session cancelled.']);
    }

    private function coordinatorSessions(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());

        return TravelLog::query()
            ->where('created_by', $request->user()->id)
            ->whereNotNull('hte_id')
            ->whereHas('hte', fn ($query) => $query->where('program_id', $programId));
    }

    private function sessionRelations(): array
    {
        return [
            'hte:id,name,address,latitude,longitude',
            'creator:id,name',
            'participants.program.college',
            'participants.hte:id,name',
            'checkpoints' => fn ($query) => $query->orderBy('sequence'),
        ];
    }

    private function authorizeCoordinatorSession(Request $request, TravelLog $travel): void
    {
        $programId = ProgramAccess::programId($request->user());
        $travel->loadMissing('hte:id,program_id');

        abort_unless(
            (int) $travel->created_by === (int) $request->user()->id
            && $travel->hte_id
            && (int) $travel->hte?->program_id === $programId,
            404
        );
    }

    private function coordinates(Request $request): array
    {
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return [
            'latitude' => (float) $coordinates['latitude'],
            'longitude' => (float) $coordinates['longitude'],
            'readable_location' => ReverseGeocoder::describe(
                (float) $coordinates['latitude'],
                (float) $coordinates['longitude'],
            ),
        ];
    }

    private function sessionPayload(TravelLog $session): array
    {
        $photoPath = $session->arrival_photo_path;

        return [
            'id' => $session->id,
            'session_code' => $session->session_code,
            'status' => $session->status,
            'start_time' => $session->start_time?->toIso8601String(),
            'arrived_at' => $session->arrived_at?->toIso8601String(),
            'end_time' => $session->end_time?->toIso8601String(),
            'hte' => $session->hte ? $this->htePayload($session->hte) : null,
            'participants' => $session->participants
                ->map(fn (Student $student) => $this->studentPayload($student))
                ->values(),
            'points' => $session->checkpoints
                ->sortBy('sequence')
                ->map(fn (TravelCheckpoint $point) => $this->pointPayload($point))
                ->values(),
            'has_arrival_photo' => (bool) $photoPath,
            'arrival_photo_endpoint' => $photoPath
                ? "/coordinator/travel/{$session->id}/arrival-photo"
                : null,
            'has_final_arrival' => $session->checkpoints->contains('is_final_destination', true),
        ];
    }

    private function htePayload(HTE $hte): array
    {
        return [
            'id' => $hte->id,
            'name' => $hte->name,
            'address' => $hte->address,
            'latitude' => $hte->latitude !== null ? (float) $hte->latitude : null,
            'longitude' => $hte->longitude !== null ? (float) $hte->longitude : null,
            'assigned_students_count' => isset($hte->assigned_students_count)
                ? (int) $hte->assigned_students_count
                : null,
        ];
    }

    private function studentPayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'school_number' => $student->student_id,
            'full_name' => trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->join(' ')),
            'program' => $student->program ? [
                'id' => $student->program->id,
                'code' => $student->program->code,
                'name' => $student->program->name,
            ] : null,
            'department' => $student->program?->college ? [
                'id' => $student->program->college->id,
                'code' => $student->program->college->code,
                'name' => $student->program->college->name,
            ] : null,
            'hte' => $student->hte ? [
                'id' => $student->hte->id,
                'name' => $student->hte->name,
            ] : null,
        ];
    }

    private function pointPayload(TravelCheckpoint $point): array
    {
        return [
            'id' => $point->id,
            'travel_session_id' => $point->travel_log_id,
            'sequence' => (int) $point->sequence,
            'point_type' => $point->point_type,
            'is_final_destination' => $point->is_final_destination,
            'label' => $point->checkpoint_name,
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
            'readable_location' => $point->readable_location,
            'captured_by' => $point->captured_by,
            'captured_at' => $point->captured_at?->toIso8601String(),
        ];
    }
}
