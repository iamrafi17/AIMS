<?php

namespace App\Support;

use App\Models\ApprovalRecord;
use App\Models\Student;
use App\Models\StudentInternship;
use App\Models\SystemNotification;
use App\Models\User;

final class DeploymentWorkflow
{
    public const PROGRAM_HEAD = 'for_program_head_review';

    public const DEAN = 'for_dean_forwarding';

    public const VPAA = 'forwarded_to_vpaa';

    public const SUPERSEDED = 'superseded';

    public const RETURNED = 'returned_to_coordinator';

    public static function snapshot(Student $student): array
    {
        $student = $student->fresh(['program', 'college', 'hte', 'hteDepartment', 'supervisorRecord',
            'currentInternship.academicSection.academicPeriod', 'currentInternship.preferredHte', 'currentInternship.preferredDepartment']);

        return [
            'student' => ['id' => $student->id, 'student_id' => $student->student_id, 'name' => $student->full_name,
                'program' => $student->program?->code, 'college' => $student->college?->code,
                'section' => $student->section, 'academic_period' => $student->currentInternship?->academicSection?->academicPeriod?->academic_year],
            'hte' => ['id' => $student->hte_id, 'name' => $student->hte?->name, 'address' => $student->hte?->address],
            'department' => ['id' => $student->hte_department_id, 'name' => $student->hteDepartment?->department_name],
            'supervisor' => ['id' => $student->supervisor_record_id, 'name' => $student->supervisorRecord?->full_name],
            'preference' => ['hte' => $student->currentInternship?->preferredHte?->name,
                'department' => $student->currentInternship?->preferredDepartment?->department_name],
            'schedule' => ['start_date' => $student->ojt_start_date?->toDateString(),
                'end_date' => $student->ojt_end_date?->toDateString(), 'required_hours' => (float) $student->required_ojt_hours,
                'allow_past_attendance' => (bool) $student->allow_past_attendance,
                'days' => EffectiveSchedule::week($student)],
        ];
    }

    // Called inside the same internship lock/transaction as the placement update.
    public static function submit(Student $student, User $coordinator): ApprovalRecord
    {
        self::withdraw($student);

        return ApprovalRecord::create([
            'subject_type' => ApprovalRecord::TYPE_DEPLOYMENT, 'subject_id' => $student->id,
            'deployment_internship_id' => $student->fresh()->currentInternship->id,
            'deployment_stage' => self::PROGRAM_HEAD, 'deployment_snapshot' => self::snapshot($student),
            'status' => 'pending', 'submitted_by' => $coordinator->id, 'submitted_at' => now(),
        ]);
    }

    public static function withdraw(Student $student): void
    {
        ApprovalRecord::where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)->where('subject_id', $student->id)
            ->where(function ($query) {
                $query->whereIn('deployment_stage', [self::PROGRAM_HEAD, self::DEAN])
                    ->orWhere(fn ($legacy) => $legacy->whereNull('deployment_stage')->where('status', 'pending'));
            })->update(['deployment_stage' => self::SUPERSEDED]);
    }

    public static function current(ApprovalRecord $approval): bool
    {
        $internship = $approval->deploymentInternship;

        return ! in_array($approval->deployment_stage, [self::SUPERSEDED, self::RETURNED], true)
            && $internship?->is_current && $internship->currentPlacement
            && ! ApprovalRecord::where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)
                ->where('subject_id', $approval->subject_id)->where('id', '>', $approval->id)
                ->where('deployment_internship_id', $internship->id)->exists();
    }

    public static function payload(ApprovalRecord $approval): array
    {
        return [
            'approval_id' => $approval->id, 'stage' => $approval->deployment_stage,
            'status' => $approval->status, 'is_current' => self::current($approval),
            ...($approval->deployment_snapshot ?? []),
            'coordinator' => $approval->deployment_snapshot['coordinator_name'] ?? $approval->submitter?->name,
            'coordinator_approved_at' => $approval->deployment_snapshot['coordinator_approved_at'] ?? $approval->submitted_at?->toIso8601String(),
            'program_head' => $approval->decisionMaker?->name, 'program_head_approved_at' => $approval->decided_at?->toIso8601String(),
            'dean' => $approval->deanForwarder?->name, 'dean_forwarded_at' => $approval->dean_forwarded_at?->toIso8601String(),
            'remarks' => $approval->remarks,
        ];
    }

    public static function notifyDeans(StudentInternship $internship): void
    {
        User::forRole('dean')->where('is_active', true)
            ->whereHas('employeeProfile', fn ($profile) => $profile->where('college_id', $internship->academicSection?->program?->college_id))
            ->pluck('id')->each(fn ($id) => SystemNotification::sendToUser($id,
                'Deployment ready for forwarding', 'A Program Head approved a student deployment. View it and forward to VPAA.',
                'approval', '/dean/deployments'));
    }

    public static function adoptLegacyPending(): void
    {
        ApprovalRecord::where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)->where('status', 'pending')
            ->whereNull('deployment_stage')->with('submitter')->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    $student = Student::find($record->subject_id);
                    if (! $student?->currentInternship?->currentPlacement) {
                        continue;
                    }
                    $isReviewed = $record->submitter?->role === 'program_head';
                    $snapshot = self::snapshot($student);
                    if ($isReviewed) {
                        $origin = ApprovalRecord::where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)
                            ->where('subject_id', $student->id)->where('id', '<', $record->id)
                            ->whereHas('submitter', fn ($user) => $user->forRole('coordinator'))->latest('id')->first();
                        $snapshot['coordinator_name'] = $origin?->submitter?->name ?? $student->currentInternship->currentPlacement->assigner?->name;
                        $snapshot['coordinator_id'] = $origin?->submitted_by ?? $student->currentInternship->currentPlacement->assigned_by;
                        $snapshot['coordinator_approved_at'] = $origin?->submitted_at?->toIso8601String();
                    }
                    $record->update(['deployment_internship_id' => $student->currentInternship->id,
                        'deployment_stage' => $isReviewed ? self::DEAN : self::PROGRAM_HEAD,
                        'deployment_snapshot' => $snapshot,
                        ...($isReviewed ? ['status' => 'approved', 'decided_by' => $record->submitted_by,
                            'decided_at' => $record->submitted_at ?? $record->created_at] : [])]);
                    if ($isReviewed) {
                        $student->currentInternship->update(['deployment_status' => 'approved']);
                    }
                }
            });
    }
}
