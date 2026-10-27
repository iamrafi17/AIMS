<?php

namespace App\Support;

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
            'creator:id,role_id',
            'coordinator:id,role_id,email',
            'hte:id,name,address,latitude,longitude',
            'participants.user:id,role_id,email,is_active',
            'participants.program.college',
            'participants.hte:id,name,address',
            'participants.hteDepartment:id,department_name',
            'checkpoints' => fn ($query) => $query->orderBy('sequence'),
            'report.coordinator:id,role_id,email',
            'report.validations.reviewer:id,role_id',
        ];
    }

    public static function coordinatorFor(Student $student): ?User
    {
        $assignedId = \Illuminate\Support\Facades\DB::table('coordinator_section_assignments')
            ->where('academic_section_id', $student->academic_section_id)->value('coordinator_id');
        if ($assignedId) {
            $assigned = User::query()->forRole('coordinator')->where('is_active', true)
                ->forAssignedProgram((int) $student->program_id)->find($assignedId);
            if ($assigned) {
                return $assigned;
            }
        }

        return null;

    }

    public static function authorizeStudent(User $user, TravelLog $travel): Student
    {
        $student = $user->student;
        abort_unless(
            $student
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
        abort_unless($travel->participants()->whereIn('students.id', ProgramAccess::studentQuery($user)->select('students.id'))->exists(), 404);
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
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'scheduled_date' => $session->scheduled_at?->toDateString(),
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
            'points' => $points->map(fn (TravelCheckpoint $point) => self::pointPayload($point, $photoBase, $session))->values(),
            'checkpoints' => $points->map(fn (TravelCheckpoint $point) => self::pointPayload($point, $photoBase, $session))->values(),
            'has_arrival_photo' => (bool) $session->arrival_photo_path,
            'arrival_photo_endpoint' => $session->arrival_photo_path ? "{$photoBase}/{$session->id}/arrival-photo" : null,
            'has_final_arrival' => $points->contains('point_type', 'final_arrival'),
            'report' => $session->report ? self::reportPayload($session->report) : null,
        ];
    }

    public static function reportPayload(TravelReport $report): array
    {
        $report->loadMissing(['coordinator:id,role_id,email', 'validations.reviewer:id,role_id']);

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
            'year_level' => $student->year_level,
            'section' => $student->section,
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
            'hte_department' => $student->hteDepartment ? [
                'id' => $student->hteDepartment->id,
                'name' => $student->hteDepartment->department_name,
            ] : null,
        ];
    }

    public static function pointPayload(TravelCheckpoint $point, string $photoBase, ?TravelLog $session = null): array
    {
        $isArrival = $point->point_type === 'final_arrival' && $session !== null;
        $photoPath = $isArrival ? $session->arrival_photo_path : $point->photo_path;
        $photoEndpoint = $isArrival
            ? "{$photoBase}/{$session->id}/arrival-photo"
            : "{$photoBase}/{$point->travel_log_id}/checkpoints/{$point->id}/photo";

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
            'has_photo' => (bool) $photoPath,
            'photo_endpoint' => $photoPath
                ? $photoEndpoint.'?v='.substr(sha1($photoPath), 0, 12)
                : null,
            'captured_at' => $point->captured_at?->toIso8601String(),
            'recorded_at' => $point->captured_at?->toIso8601String(),
        ];
    }
}
