<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\ApprovalRecord;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\Student;

final class SmartClockAccess
{
    public static function status(Student $student, bool $allowCurrentTimeOut = false): array
    {
        $student = $student->fresh();
        $program = $student->program_id ? Program::find($student->program_id) : null;
        $programEnabled = (bool) ($program?->smart_clock_enabled ?? false);
        $consentAccepted = $student->consent_status === 'done'
            && InternshipRequirement::query()
                ->where('student_id', $student->id)
                ->whereRaw('LOWER(requirement_name) LIKE ?', ['%consent%'])
                ->whereNotNull('file_path')
                ->where('status', 'approved')
                ->exists();
        $canFinishSession = false;
        // Newly submitted/adopted deployments must finish Program Head review and reach the OJT start date.
        // Historical deployments without a handoff record retain their existing access policy.
        $tracked = ApprovalRecord::where('deployment_internship_id', $student->currentInternship?->id)
            ->where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)->whereNotNull('deployment_stage')->exists();
        if ($tracked && $student->schedule_status === 'approved' && $student->internship_status === 'pending'
            && $student->ojt_start_date?->lte(today())) {
            DeploymentActivation::activateDue();
            $student = $student->fresh();
        }
        $deploymentReady = ! $tracked || ($student->hte_id && $student->schedule_status === 'approved'
            && $student->internship_status === 'active' && $student->ojt_start_date && $student->ojt_start_date->lte(today()));
        if ($allowCurrentTimeOut && (! $programEnabled || ! $consentAccepted || ! $deploymentReady)) {
            $attendance = Attendance::forStudent($student->id)
                ->where('student_internship_id', $student->currentInternship?->id)
                ->whereDate('date', now()->toDateString())
                ->first();
            $canFinishSession = $attendance
                && in_array($attendance->status, ['present', 'late'], true)
                && (($attendance->am_time_in && ! $attendance->am_time_out)
                    || ($attendance->pm_time_in && ! $attendance->pm_time_out));
        }

        return [
            'available' => ($programEnabled && $consentAccepted && $deploymentReady) || $canFinishSession,
            'program_enabled' => $programEnabled,
            'consent_accepted' => $consentAccepted,
            'deployment_ready' => (bool) $deploymentReady,
            'can_finish_session' => (bool) $canFinishSession,
            'program_code' => $program?->code,
            'message' => $canFinishSession
                ? 'Finish your current timed-in session. New Time Ins are unavailable until Smart Clock access is restored.'
                : (! $deploymentReady ? 'Your deployment must be approved by the Program Head and reach its OJT start date before recording attendance.' : (! $programEnabled
                ? 'The Quick Smart Clock for your program is currently disabled.'
                : (! $consentAccepted ? 'Your consent must be accepted by your coordinator before using the Quick Smart Clock.' : null))),
        ];
    }

    public static function authorize(Student $student, bool $allowCurrentTimeOut = false): void
    {
        $status = self::status($student, $allowCurrentTimeOut);
        abort_unless($status['available'], 403, $status['message']);
    }
}
