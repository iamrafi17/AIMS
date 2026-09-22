<?php

namespace App\Models;

use App\Support\AttendanceRecordNormalizer;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Attendance extends Model
{
    use HasFactory;

    private const DERIVED_ATTENDANCE_ATTRIBUTES = [
        'student_id', 'time_in', 'time_out',
        'am_time_in', 'am_late_minutes', 'am_time_out',
        'pm_time_in', 'pm_late_minutes', 'pm_time_out',
        'ot_start', 'ot_end', 'overtime_hours',
        'am_activity', 'pm_activity',
        'journal_status', 'journal_feedback', 'journal_reviewed_by', 'journal_reviewed_at',
        'am_time_in_location', 'am_time_out_location', 'pm_time_in_location', 'pm_time_out_location',
        'latitude_in', 'longitude_in', 'latitude_out', 'longitude_out',
        'is_verified', 'supervisor_review_status', 'supervisor_feedback',
        'supervisor_reviewed_by', 'supervisor_reviewed_at', 'verified_by', 'verified_at',
    ];

    protected array $attendanceInput = [];

    protected array $attendanceCache = [];

    protected $table = 'attendance';

    protected $fillable = [
        'student_id',
        'student_internship_id',
        'holiday_id',
        'date',
        'time_in',
        'time_out',
        'am_time_in',
        'am_late_minutes',
        'am_time_out',
        'pm_time_in',
        'pm_late_minutes',
        'pm_time_out',
        'ot_start',
        'ot_end',
        'overtime_hours',
        'am_activity',
        'pm_activity',
        'journal_status',
        'journal_feedback',
        'journal_reviewed_by',
        'journal_reviewed_at',
        'am_time_in_location',
        'am_time_out_location',
        'pm_time_in_location',
        'pm_time_out_location',
        'work_mode',
        'session_type',
        'status',
        'latitude_in',
        'longitude_in',
        'latitude_out',
        'longitude_out',
        'is_verified',
        'supervisor_review_status',
        'supervisor_feedback',
        'supervisor_reviewed_by',
        'supervisor_reviewed_at',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Attendance $attendance): void {
            if ($attendance->student_internship_id && $attendance->student_id) {
                $internshipStudentId = StudentInternship::query()
                    ->whereKey($attendance->student_internship_id)
                    ->value('student_id');

                if ((int) $internshipStudentId !== (int) $attendance->student_id) {
                    throw new \LogicException('The attendance internship does not belong to the selected student.');
                }
            }

            if (! $attendance->student_internship_id && $attendance->student_id) {
                $attendance->student_internship_id = StudentInternship::query()
                    ->where('student_id', $attendance->student_id)
                    ->where('is_current', true)
                    ->latest('id')
                    ->value('id');
            }

            if (! $attendance->student_internship_id) {
                throw new \LogicException('Attendance requires a current student internship.');
            }
        });

        static::saved(fn (Attendance $attendance) => AttendanceRecordNormalizer::sync($attendance));
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, self::DERIVED_ATTENDANCE_ATTRIBUTES, true)) {
            $this->attendanceInput[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        if (in_array($key, self::DERIVED_ATTENDANCE_ATTRIBUTES, true)) {
            if (array_key_exists($key, $this->attendanceInput)) {
                return $this->castAttendanceInput($key, $this->attendanceInput[$key]);
            }

            return $this->resolveAttendanceAttribute($key);
        }

        return parent::getAttribute($key);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach (self::DERIVED_ATTENDANCE_ATTRIBUTES as $key) {
            $value = $this->getAttribute($key);
            $attributes[$key] = $value instanceof DateTimeInterface ? $this->serializeDate($value) : $value;
        }

        return $attributes;
    }

    public function normalizedAttendanceInput(): array
    {
        return $this->attendanceInput;
    }

    public function hasNormalizedAttendanceInput(array $keys = []): bool
    {
        return $keys === []
            ? $this->attendanceInput !== []
            : collect($keys)->contains(fn (string $key) => array_key_exists($key, $this->attendanceInput));
    }

    public function clearNormalizedAttendanceInput(): void
    {
        $this->attendanceInput = [];
        $this->attendanceCache = [];
        foreach (['internship', 'events', 'scheduleSnapshots', 'journals', 'reviews', 'student'] as $relation) {
            $this->unsetRelation($relation);
        }
    }

    private function resolveAttendanceAttribute(string $key): mixed
    {
        $events = fn () => $this->attendanceRecord('events', fn () => $this->events()->get()->keyBy('event_type'));
        $event = fn (string $type) => $events()->get($type);
        $journals = fn () => $this->attendanceRecord('journals', fn () => $this->journals()->get()->keyBy('period'));
        $reviews = fn () => $this->attendanceRecord('reviews', fn () => $this->reviews()->get()->keyBy('review_type'));
        $snapshots = fn () => $this->attendanceRecord('snapshots', fn () => $this->scheduleSnapshots()->get()->keyBy('period'));
        $firstIn = fn () => collect([$event('am_in'), $event('pm_in')])->filter()->sortBy('occurred_at')->first();
        $lastOut = fn () => collect([$event('am_out'), $event('pm_out')])->filter()->sortByDesc('occurred_at')->first();
        $journal = fn (string $period) => $journals()->get($period);
        $latestJournal = fn () => $journals()->sortByDesc('reviewed_at')->first();
        $verification = fn () => $reviews()->get('verification');
        $supervisor = fn () => $reviews()->get('supervisor');

        return match ($key) {
            'student_id' => $this->internship?->student_id,
            'time_in' => $firstIn()?->occurred_at,
            'time_out' => $lastOut()?->occurred_at,
            'am_time_in' => $event('am_in')?->occurred_at,
            'am_time_out' => $event('am_out')?->occurred_at,
            'pm_time_in' => $event('pm_in')?->occurred_at,
            'pm_time_out' => $event('pm_out')?->occurred_at,
            'ot_start' => $event('ot_start')?->occurred_at,
            'ot_end' => $event('ot_end')?->occurred_at,
            'am_time_in_location' => $this->eventLocation($event('am_in')),
            'am_time_out_location' => $this->eventLocation($event('am_out')),
            'pm_time_in_location' => $this->eventLocation($event('pm_in')),
            'pm_time_out_location' => $this->eventLocation($event('pm_out')),
            'latitude_in' => $firstIn()?->latitude,
            'longitude_in' => $firstIn()?->longitude,
            'latitude_out' => $lastOut()?->latitude,
            'longitude_out' => $lastOut()?->longitude,
            'am_late_minutes' => $this->lateMinutesFor($event('am_in'), $snapshots()->get('am')),
            'pm_late_minutes' => $this->lateMinutesFor($event('pm_in'), $snapshots()->get('pm')),
            'overtime_hours' => number_format($this->overtimeHours($events(), $snapshots()), 2, '.', ''),
            'am_activity' => $journal('am')?->activity,
            'pm_activity' => $journal('pm')?->activity,
            'journal_status' => $journals()->isNotEmpty() && $journals()->every(fn ($item) => $item->review_status === 'reviewed') ? 'reviewed' : 'pending',
            'journal_feedback' => $latestJournal()?->feedback,
            'journal_reviewed_by' => $latestJournal()?->reviewed_by,
            'journal_reviewed_at' => $latestJournal()?->reviewed_at,
            'is_verified' => $verification()?->decision === 'approved',
            'verified_by' => $verification()?->reviewed_by,
            'verified_at' => $verification()?->reviewed_at,
            'supervisor_review_status' => $supervisor()?->decision ?? 'pending',
            'supervisor_feedback' => $supervisor()?->feedback,
            'supervisor_reviewed_by' => $supervisor()?->reviewed_by,
            'supervisor_reviewed_at' => $supervisor()?->reviewed_at,
            default => null,
        };
    }

    private function attendanceRecord(string $key, callable $resolver): mixed
    {
        return $this->attendanceCache[$key] ??= $resolver();
    }

    private function eventLocation(?AttendanceEvent $event): ?array
    {
        if (! $event || ($event->latitude === null && $event->longitude === null && ! $event->readable_location)) {
            return null;
        }

        return [
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'accuracy' => $event->accuracy,
            'address' => $event->readable_location,
        ];
    }

    private function lateMinutesFor(
        ?AttendanceEvent $event,
        ?AttendanceScheduleSnapshot $snapshot,
    ): int {
        if (! $event || ! $snapshot?->scheduled_start || ! $snapshot->is_working || ! $this->date) {
            return 0;
        }

        $scheduled = Carbon::parse($this->date->toDateString().' '.$snapshot->scheduled_start);
        $actual = $event->occurred_at->copy()->startOfMinute();
        if ($actual->lte($scheduled->copy()->addMinutes($snapshot->grace_period_minutes))) {
            return 0;
        }

        return (int) $scheduled->diffInMinutes($actual);
    }

    private function overtimeHours(Collection $events, Collection $snapshots): float
    {
        $start = $events->get('ot_start')?->occurred_at
            ?? collect([$events->get('am_in'), $events->get('pm_in')])->filter()->sortBy('occurred_at')->first()?->occurred_at;
        $end = $events->get('ot_end')?->occurred_at
            ?? collect([$events->get('am_out'), $events->get('pm_out')])->filter()->sortByDesc('occurred_at')->first()?->occurred_at;
        if (! $start || ! $end || ! $end->gt($start) || ! $this->date) {
            return 0;
        }

        [$startPeriod, $endPeriod] = match ($this->session_type) {
            'am_half' => ['am', 'am'],
            'pm_half' => ['pm', 'pm'],
            default => ['am', 'pm'],
        };
        $scheduleStartValue = $snapshots->get($startPeriod)?->scheduled_start;
        $scheduleEndValue = $snapshots->get($endPeriod)?->scheduled_end;
        if (! $scheduleStartValue || ! $scheduleEndValue) {
            return 0;
        }

        $scheduleStart = Carbon::parse($this->date->toDateString().' '.$scheduleStartValue);
        $scheduleEnd = Carbon::parse($this->date->toDateString().' '.$scheduleEndValue);
        $overlapStart = $start->gt($scheduleStart) ? $start : $scheduleStart;
        $overlapEnd = $end->lt($scheduleEnd) ? $end : $scheduleEnd;
        $overlap = $overlapEnd->gt($overlapStart) ? $overlapStart->diffInSeconds($overlapEnd) : 0;
        $hours = max(($start->diffInSeconds($end) - $overlap) / 3600, 0);

        return $hours >= 0.5 ? round($hours, 2) : 0;
    }

    private function castAttendanceInput(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($key) {
            'student_id', 'am_late_minutes', 'pm_late_minutes', 'journal_reviewed_by',
            'supervisor_reviewed_by', 'verified_by' => (int) $value,
            'is_verified' => (bool) $value,
            'overtime_hours' => number_format((float) $value, 2, '.', ''),
            'time_in', 'time_out', 'am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out',
            'ot_start', 'ot_end', 'journal_reviewed_at', 'supervisor_reviewed_at', 'verified_at' => $this->asDateTime($value),
            default => $value,
        };
    }

    public function student()
    {
        return $this->hasOneThrough(
            Student::class,
            StudentInternship::class,
            'id',
            'id',
            'student_internship_id',
            'student_id',
        );
    }

    public function internship()
    {
        return $this->belongsTo(StudentInternship::class, 'student_internship_id');
    }

    public function holiday()
    {
        return $this->belongsTo(Holiday::class);
    }

    public function events()
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function scheduleSnapshots()
    {
        return $this->hasMany(AttendanceScheduleSnapshot::class);
    }

    public function journals()
    {
        return $this->hasMany(AttendanceJournal::class);
    }

    public function reviews()
    {
        return $this->hasMany(AttendanceReview::class);
    }

    public function verifier()
    {
        return $this->hasOneThrough(
            User::class,
            AttendanceReview::class,
            'attendance_id',
            'id',
            'id',
            'reviewed_by',
        )->where('attendance_reviews.review_type', 'verification');
    }

    public function journalReviewer()
    {
        return $this->hasOneThrough(
            User::class,
            AttendanceJournal::class,
            'attendance_id',
            'id',
            'id',
            'reviewed_by',
        )->whereNotNull('attendance_journals.reviewed_by');
    }

    public function supervisorReviewer()
    {
        return $this->hasOneThrough(
            User::class,
            AttendanceReview::class,
            'attendance_id',
            'id',
            'id',
            'reviewed_by',
        )->where('attendance_reviews.review_type', 'supervisor');
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->whereHas('internship', fn (Builder $internship) => $internship->where('student_id', $studentId));
    }

    public function scopeForStudents(Builder $query, iterable $studentIds): Builder
    {
        return $query->whereHas('internship', fn (Builder $internship) => $internship->whereIn('student_id', $studentIds));
    }

    public function scopeHasJournals(Builder $query): Builder
    {
        return $query->whereHas('journals');
    }

    public function scopeWithoutJournals(Builder $query): Builder
    {
        return $query->whereDoesntHave('journals');
    }

    public function scopeWithJournalStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('journals', fn (Builder $journals) => $journals->where('review_status', $status));
    }

    public function scopeVerified(Builder $query, bool $verified = true): Builder
    {
        return $verified
            ? $query->whereHas('reviews', fn (Builder $reviews) => $reviews
                ->where('review_type', 'verification')->where('decision', 'approved'))
            : $query->whereDoesntHave('reviews', fn (Builder $reviews) => $reviews
                ->where('review_type', 'verification')->where('decision', 'approved'));
    }

    public function scopeForSupervisorReviewStatus(Builder $query, string $status): Builder
    {
        return $status === 'pending'
            ? $query->whereDoesntHave('reviews', fn (Builder $reviews) => $reviews
                ->where('review_type', 'supervisor')->where('decision', '!=', 'pending'))
            : $query->whereHas('reviews', fn (Builder $reviews) => $reviews
                ->where('review_type', 'supervisor')->where('decision', $status));
    }
}
