<?php

namespace App\Support;

use App\Models\OjtEnrollment;
use App\Models\Student;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use App\Models\TravelReport;
use App\Models\User;

final class TravelWorkflow
{
    public static function relations(): array
    {
        return [
            'creator:id,name',
            'coordinator:id,name,email',
            'hte:id,name,address,latitude,longitude',
            'participants.user:id,name,email,is_active',
            'participants.program.college',
            'participants.hte:id,name,address',
            'checkpoints' => fn ($query) => $query->orderBy('sequence'),
            'report.coordinator:id,name,email',
            'report.validations.reviewer:id,name,role',
        ];
    }

    public static function coordinatorFor(Student $student): ?User
    {
        $enrollmentCoordinator = OjtEnrollment::query()
            ->where('student_record_id', $student->id)
            ->with('coordinator:id,name,role,is_active,college_id,program_id')
            ->first()?->coordinator;

        if ($enrollmentCoordinator
            && $enrollmentCoordinator->role === 'coordinator'
            && $enrollmentCoordinator->is_active
            && (int) $enrollmentCoordinator->program_id === (int) $student->program_id) {
            return $enrollmentCoordinator;
        }

        return User::query()
            ->where('role', 'coordinator')
            ->where('is_active', true)
            ->where('college_id', $student->college_id)
            ->where('program_id', $student->program_id)
            ->orderBy('id')
            ->first();
    }

    public static function authorizeStudent(User $user, TravelLog $travel): Student
    {
        $student = $user->student;
        abort_unless(
            $student
            && (int) $travel->created_by === (int) $user->id
            && $travel->participants()->whereKey($student->id)->exists(),
            404,
        );

        return $student;
    }

    public static function authorizeCoordinator(User $user, TravelLog $travel): void
    {
        ProgramAccess::programId($user);
        abort_unless((int) $travel->coordinator_id === (int) $user->id, 404);
        ProgramAccess::authorizeTravel($user, $travel);
    }

    public static function sessionPayload(TravelLog $session, string $photoBase): array
    {
        $session->loadMissing(self::relations());
        $points = $session->checkpoints->sortBy('sequence')->values();
        $student = $session->participants->first();

        return [
            'id' => $session->id,
            'session_code' => $session->session_code,
            'status' => $session->status,
            'travel_date' => $session->start_time?->toDateString(),
            'start_time' => $session->start_time?->toIso8601String(),
            'departed_at' => $session->departed_at?->toIso8601String(),
            'arrived_at' => $session->arrived_at?->toIso8601String(),
            'end_time' => $session->end_time?->toIso8601String(),
            'last_location_update' => $points->last()?->captured_at?->toIso8601String(),
            'creator' => $session->creator ? ['id' => $session->creator->id, 'name' => $session->creator->name] : null,
            'coordinator' => $session->coordinator ? ['id' => $session->coordinator->id, 'name' => $session->coordinator->name] : null,
            'hte' => $session->hte ? [
                'id' => $session->hte->id,
                'name' => $session->hte->name,
                'address' => $session->hte->address,
                'latitude' => $session->hte->latitude !== null ? (float) $session->hte->latitude : null,
                'longitude' => $session->hte->longitude !== null ? (float) $session->hte->longitude : null,
            ] : null,
            'student' => $student ? self::studentPayload($student) : null,
            'participants' => $session->participants->map(fn (Student $participant) => self::studentPayload($participant))->values(),
            'points' => $points->map(fn (TravelCheckpoint $point) => self::pointPayload($point))->values(),
            'checkpoints' => $points->map(fn (TravelCheckpoint $point) => self::pointPayload($point))->values(),
            'has_arrival_photo' => (bool) $session->arrival_photo_path,
            'arrival_photo_endpoint' => $session->arrival_photo_path ? "{$photoBase}/{$session->id}/arrival-photo" : null,
            'has_final_arrival' => $points->contains('point_type', 'final_arrival'),
            'report' => $session->report ? self::reportPayload($session->report) : null,
        ];
    }

    public static function reportPayload(TravelReport $report): array
    {
        $report->loadMissing(['coordinator:id,name,email', 'validations.reviewer:id,name,role']);

        return [
            'id' => $report->id,
            'travel_log_id' => $report->travel_log_id,
            'report_number' => $report->report_number,
            'remarks' => $report->remarks,
            'status' => $report->status,
            'version' => $report->version,
            'generated_at' => $report->generated_at?->toIso8601String(),
            'submitted_at' => $report->submitted_at?->toIso8601String(),
            'program_head_validated_at' => $report->program_head_validated_at?->toIso8601String(),
            'forwarded_at' => $report->forwarded_at?->toIso8601String(),
            'final_validated_at' => $report->final_validated_at?->toIso8601String(),
            'coordinator' => $report->coordinator ? ['id' => $report->coordinator->id, 'name' => $report->coordinator->name] : null,
            'validations' => $report->validations->map(fn ($validation) => [
                'id' => $validation->id,
                'reviewer' => $validation->reviewer?->name,
                'reviewer_role' => $validation->reviewer_role,
                'action' => $validation->action,
                'reason' => $validation->reason,
                'comments' => $validation->comments,
                'reviewed_at' => $validation->reviewed_at?->toIso8601String(),
                'report_version' => $validation->report_version,
            ])->values(),
        ];
    }

    public static function studentPayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'school_number' => $student->student_id,
            'student_id' => $student->student_id,
            'full_name' => trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->join(' ')),
            'name' => trim($student->first_name.' '.$student->last_name),
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
                'address' => $student->hte->address,
            ] : null,
        ];
    }

    public static function pointPayload(TravelCheckpoint $point): array
    {
        return [
            'id' => $point->id,
            'travel_log_id' => $point->travel_log_id,
            'sequence' => (int) $point->sequence,
            'event_type' => $point->point_type,
            'point_type' => $point->point_type,
            'checkpoint_name' => $point->checkpoint_name,
            'label' => $point->checkpoint_name,
            'is_final_destination' => (bool) $point->is_final_destination,
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
            'gps_accuracy' => $point->gps_accuracy !== null ? (float) $point->gps_accuracy : null,
            'readable_location' => $point->readable_location,
            'remarks' => $point->notes,
            'notes' => $point->notes,
            'captured_at' => $point->captured_at?->toIso8601String(),
            'recorded_at' => $point->captured_at?->toIso8601String(),
        ];
    }
}
