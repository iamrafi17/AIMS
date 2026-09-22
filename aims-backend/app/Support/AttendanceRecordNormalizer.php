<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\AttendanceJournal;
use App\Models\AttendanceReview;
use App\Models\AttendanceScheduleSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceRecordNormalizer
{
    private const EVENT_FIELDS = [
        'am_time_in' => 'am_in', 'am_time_out' => 'am_out',
        'pm_time_in' => 'pm_in', 'pm_time_out' => 'pm_out',
        'ot_start' => 'ot_start', 'ot_end' => 'ot_end',
    ];

    public static function sync(Attendance $attendance): void
    {
        if (! Schema::hasTable('attendance_events')) {
            return;
        }

        DB::transaction(function () use ($attendance): void {
            self::ensureScheduleSnapshots($attendance);
            $input = $attendance->normalizedAttendanceInput();
            self::syncEvents($attendance, $input);
            self::syncJournals($attendance, $input);
            self::syncReviews($attendance, $input);
            $attendance->clearNormalizedAttendanceInput();
        });
    }

    private static function ensureScheduleSnapshots(Attendance $attendance): void
    {
        if ($attendance->scheduleSnapshots()->count() === 2) {
            return;
        }

        $student = $attendance->internship?->student;
        if (! $student || ! $attendance->date) {
            return;
        }

        $schedule = EffectiveSchedule::forDate($student, $attendance->date);
        $day = strtolower($attendance->date->format('D'));
        $overrideId = $attendance->internship->scheduleOverrides()->where('day_of_week', $day)->value('id');
        $batchId = ! $overrideId && $attendance->internship->hte_id && $attendance->internship->academic_section_id
            ? DB::table('hte_batch_schedules')
                ->where('hte_id', $attendance->internship->hte_id)
                ->where('academic_section_id', $attendance->internship->academic_section_id)
                ->where('day_of_week', $day)
                ->value('id')
            : null;
        $hteScheduleId = ! $overrideId && ! $batchId && $attendance->internship->hte_id
            ? DB::table('hte_schedules')
                ->where('hte_id', $attendance->internship->hte_id)
                ->where('day_of_week', $day)
                ->value('id')
            : null;

        foreach (['am', 'pm'] as $period) {
            AttendanceScheduleSnapshot::updateOrCreate(
                ['attendance_id' => $attendance->id, 'period' => $period],
                [
                    'is_working' => $schedule['is_working'],
                    'scheduled_start' => $schedule[$period.'_start'],
                    'scheduled_end' => $schedule[$period.'_end'],
                    'grace_period_minutes' => $schedule['grace_period_minutes'],
                    'source' => $schedule['source'],
                    'hte_schedule_id' => $hteScheduleId,
                    'hte_batch_schedule_id' => $batchId,
                    'student_schedule_override_id' => $overrideId,
                ],
            );
        }
    }

    private static function syncEvents(Attendance $attendance, array $input): void
    {
        foreach (self::EVENT_FIELDS as $field => $eventType) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            if ($input[$field] === null) {
                $attendance->events()->where('event_type', $eventType)->delete();

                continue;
            }

            $location = self::locationFor($field, $input);
            AttendanceEvent::updateOrCreate(
                ['attendance_id' => $attendance->id, 'event_type' => $eventType],
                [
                    'occurred_at' => $input[$field],
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'accuracy' => $location['accuracy'] ?? null,
                    'readable_location' => $location['address'] ?? $location['readable_location'] ?? null,
                    'source' => 'system',
                ],
            );
        }

        $specificIn = array_key_exists('am_time_in', $input) || array_key_exists('pm_time_in', $input);
        $specificOut = array_key_exists('am_time_out', $input) || array_key_exists('pm_time_out', $input);
        if (array_key_exists('time_in', $input) && ! $specificIn) {
            self::syncGenericEvent($attendance, $input, 'time_in', $attendance->session_type === 'pm_half' ? 'pm_in' : 'am_in');
        }
        if (array_key_exists('time_out', $input) && ! $specificOut) {
            self::syncGenericEvent($attendance, $input, 'time_out', $attendance->session_type === 'am_half' ? 'am_out' : 'pm_out');
        }
    }

    private static function syncGenericEvent(Attendance $attendance, array $input, string $field, string $eventType): void
    {
        if ($input[$field] === null) {
            $attendance->events()->where('event_type', $eventType)->delete();

            return;
        }

        $isIn = str_ends_with($field, '_in');
        AttendanceEvent::updateOrCreate(
            ['attendance_id' => $attendance->id, 'event_type' => $eventType],
            [
                'occurred_at' => $input[$field],
                'latitude' => $input[$isIn ? 'latitude_in' : 'latitude_out'] ?? null,
                'longitude' => $input[$isIn ? 'longitude_in' : 'longitude_out'] ?? null,
                'source' => 'system',
            ],
        );
    }

    private static function locationFor(string $field, array $input): array
    {
        $location = $input[$field.'_location'] ?? [];
        if (is_string($location)) {
            $location = json_decode($location, true) ?: [];
        }

        return is_array($location) ? $location : [];
    }

    private static function syncJournals(Attendance $attendance, array $input): void
    {
        foreach (['am', 'pm'] as $period) {
            $field = $period.'_activity';
            if (! array_key_exists($field, $input)) {
                continue;
            }

            if (blank($input[$field])) {
                $attendance->journals()->where('period', $period)->delete();

                continue;
            }

            AttendanceJournal::updateOrCreate(
                ['attendance_id' => $attendance->id, 'period' => $period],
                ['activity' => $input[$field]],
            );
        }

        $reviewFields = ['journal_status', 'journal_feedback', 'journal_reviewed_by', 'journal_reviewed_at'];
        if (collect($reviewFields)->contains(fn (string $field) => array_key_exists($field, $input))) {
            $attendance->journals()->update([
                'review_status' => $input['journal_status'] ?? 'pending',
                'feedback' => $input['journal_feedback'] ?? null,
                'reviewed_by' => $input['journal_reviewed_by'] ?? null,
                'reviewed_at' => $input['journal_reviewed_at'] ?? null,
            ]);
        }
    }

    private static function syncReviews(Attendance $attendance, array $input): void
    {
        if (collect(['supervisor_review_status', 'supervisor_feedback', 'supervisor_reviewed_by', 'supervisor_reviewed_at'])
            ->contains(fn (string $field) => array_key_exists($field, $input))) {
            AttendanceReview::updateOrCreate(
                ['attendance_id' => $attendance->id, 'review_type' => 'supervisor'],
                [
                    'decision' => $input['supervisor_review_status'] ?? 'pending',
                    'feedback' => $input['supervisor_feedback'] ?? null,
                    'reviewed_by' => $input['supervisor_reviewed_by'] ?? null,
                    'reviewed_at' => $input['supervisor_reviewed_at'] ?? null,
                ],
            );
        }

        if (collect(['is_verified', 'verified_by', 'verified_at'])->contains(fn (string $field) => array_key_exists($field, $input))) {
            AttendanceReview::updateOrCreate(
                ['attendance_id' => $attendance->id, 'review_type' => 'verification'],
                [
                    'decision' => ($input['is_verified'] ?? false) ? 'approved' : 'pending',
                    'reviewed_by' => $input['verified_by'] ?? null,
                    'reviewed_at' => $input['verified_at'] ?? null,
                ],
            );
        }
    }
}
