<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\InternshipRequirement;
use App\Models\Program;
use App\Models\Student;

final class SmartClockAccess
{
    public static function status(Student $student, bool $allowCurrentTimeOut = false): array
    {
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
        if ($allowCurrentTimeOut && (! $programEnabled || ! $consentAccepted)) {
            $attendance = Attendance::forStudent($student->id)
                ->whereDate('date', now()->toDateString())
                ->first();
            $canFinishSession = $attendance
                && in_array($attendance->status, ['present', 'late'], true)
                && (($attendance->am_time_in && ! $attendance->am_time_out)
                    || ($attendance->pm_time_in && ! $attendance->pm_time_out));
        }

        return [
            'available' => ($programEnabled && $consentAccepted) || $canFinishSession,
            'program_enabled' => $programEnabled,
            'consent_accepted' => $consentAccepted,
            'can_finish_session' => (bool) $canFinishSession,
            'program_code' => $program?->code,
            'message' => $canFinishSession
                ? 'Finish your current timed-in session. New Time Ins are unavailable until Smart Clock access is restored.'
                : (! $programEnabled
                ? 'The Quick Smart Clock for your program is currently disabled.'
                : (! $consentAccepted ? 'Your consent must be accepted by your coordinator before using the Quick Smart Clock.' : null)),
        ];
    }

    public static function authorize(Student $student, bool $allowCurrentTimeOut = false): void
    {
        $status = self::status($student, $allowCurrentTimeOut);
        abort_unless($status['available'], 403, $status['message']);
    }
}
