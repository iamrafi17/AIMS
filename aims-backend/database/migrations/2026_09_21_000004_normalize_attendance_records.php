<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EVENT_COLUMNS = [
        'am_in' => ['am_time_in', 'am_time_in_location'],
        'am_out' => ['am_time_out', 'am_time_out_location'],
        'pm_in' => ['pm_time_in', 'pm_time_in_location'],
        'pm_out' => ['pm_time_out', 'pm_time_out_location'],
        'ot_start' => ['ot_start', null],
        'ot_end' => ['ot_end', null],
    ];

    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->foreignId('holiday_id')->nullable()->after('status')->constrained('holidays')->nullOnDelete();
        });

        Schema::create('attendance_schedule_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->enum('period', ['am', 'pm']);
            $table->boolean('is_working')->default(true);
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->unsignedSmallInteger('grace_period_minutes')->default(10);
            $table->string('source', 30)->default('system_default');
            $table->foreignId('hte_batch_schedule_id')->nullable();
            $table->foreignId('student_schedule_override_id')->nullable();
            $table->timestamps();

            $table->foreign('hte_batch_schedule_id', 'attendance_schedule_batch_fk')
                ->references('id')->on('hte_batch_schedules')->nullOnDelete();
            $table->foreign('student_schedule_override_id', 'attendance_schedule_override_fk')
                ->references('id')->on('student_schedule_overrides')->nullOnDelete();
            $table->unique(['attendance_id', 'period']);
        });

        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->enum('event_type', ['am_in', 'am_out', 'pm_in', 'pm_out', 'ot_start', 'ot_end']);
            $table->timestamp('occurred_at');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->text('readable_location')->nullable();
            $table->string('source', 30)->default('system');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['attendance_id', 'event_type']);
            $table->index(['occurred_at', 'event_type']);
        });

        Schema::create('attendance_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->enum('period', ['am', 'pm']);
            $table->text('activity');
            $table->string('review_status', 20)->default('pending');
            $table->text('feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['attendance_id', 'period']);
            $table->index(['review_status', 'reviewed_at']);
        });

        Schema::create('attendance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->enum('review_type', ['supervisor', 'verification']);
            $table->string('decision', 20)->default('pending');
            $table->text('feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['attendance_id', 'review_type']);
            $table->index(['review_type', 'decision']);
        });

        $this->backfill();
    }

    private function backfill(): void
    {
        DB::table('attendance')->orderBy('id')->get()->each(function (object $attendance): void {
            $this->backfillSchedule($attendance);
            $this->backfillEvents($attendance);
            $this->backfillJournals($attendance);
            $this->backfillReviews($attendance);
        });
    }

    private function backfillSchedule(object $attendance): void
    {
        $internship = DB::table('student_internships')->where('id', $attendance->student_internship_id)->first();
        $day = strtolower(Carbon::parse($attendance->date)->format('D'));
        $override = DB::table('student_schedule_overrides')
            ->where('student_internship_id', $attendance->student_internship_id)
            ->where('day_of_week', $day)
            ->first();
        $batch = ! $override && $internship?->hte_id && $internship?->academic_section_id
            ? DB::table('hte_batch_schedules')
                ->where('hte_id', $internship->hte_id)
                ->where('academic_section_id', $internship->academic_section_id)
                ->where('day_of_week', $day)
                ->first()
            : null;
        $hte = ! $override && ! $batch && $internship?->hte_id
            ? DB::table('htes')->where('id', $internship->hte_id)->first()
            : null;
        $schedule = $override ?: $batch ?: $hte;
        $source = $override ? 'student_override' : ($batch ? 'batch_schedule' : ($hte ? 'hte_default' : 'system_default'));
        $isWorking = $override || $batch ? (bool) $schedule->is_working : true;

        foreach (['am', 'pm'] as $period) {
            DB::table('attendance_schedule_snapshots')->insert([
                'attendance_id' => $attendance->id,
                'period' => $period,
                'is_working' => $isWorking,
                'scheduled_start' => $schedule?->{$period.'_start'} ?? ($period === 'am' ? '08:00' : '13:00'),
                'scheduled_end' => $schedule?->{$period.'_end'} ?? ($period === 'am' ? '12:00' : '17:00'),
                'grace_period_minutes' => $batch?->grace_period_minutes ?? 10,
                'source' => $source,
                'hte_batch_schedule_id' => $batch?->id,
                'student_schedule_override_id' => $override?->id,
                'created_at' => $attendance->created_at,
                'updated_at' => $attendance->updated_at,
            ]);
        }
    }

    private function backfillEvents(object $attendance): void
    {
        $hasPeriodEvents = $attendance->am_time_in || $attendance->am_time_out || $attendance->pm_time_in || $attendance->pm_time_out;

        foreach (self::EVENT_COLUMNS as $eventType => [$timeColumn, $locationColumn]) {
            if (! $attendance->{$timeColumn}) {
                continue;
            }

            $this->insertEvent($attendance, $eventType, $attendance->{$timeColumn}, $locationColumn ? $attendance->{$locationColumn} : null);
        }

        if (! $hasPeriodEvents && $attendance->time_in) {
            $period = $attendance->session_type === 'pm_half' ? 'pm' : 'am';
            $this->insertEvent($attendance, $period.'_in', $attendance->time_in, null, true);
        }
        if (! $hasPeriodEvents && $attendance->time_out) {
            $period = $attendance->session_type === 'am_half' ? 'am' : 'pm';
            $this->insertEvent($attendance, $period.'_out', $attendance->time_out, null, false);
        }
    }

    private function insertEvent(object $attendance, string $eventType, string $occurredAt, mixed $rawLocation, ?bool $isGenericIn = null): void
    {
        $location = is_string($rawLocation) ? json_decode($rawLocation, true) : (array) ($rawLocation ?? []);
        $isIn = $isGenericIn ?? str_ends_with($eventType, '_in');

        DB::table('attendance_events')->insertOrIgnore([
            'attendance_id' => $attendance->id,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt,
            'latitude' => $location['latitude'] ?? ($isIn ? $attendance->latitude_in : $attendance->latitude_out),
            'longitude' => $location['longitude'] ?? ($isIn ? $attendance->longitude_in : $attendance->longitude_out),
            'accuracy' => $location['accuracy'] ?? null,
            'readable_location' => $location['address'] ?? $location['readable_location'] ?? null,
            'source' => 'legacy_import',
            'created_at' => $attendance->created_at,
            'updated_at' => $attendance->updated_at,
        ]);
    }

    private function backfillJournals(object $attendance): void
    {
        foreach (['am', 'pm'] as $period) {
            $activity = $attendance->{$period.'_activity'};
            if (! $activity) {
                continue;
            }

            DB::table('attendance_journals')->insert([
                'attendance_id' => $attendance->id,
                'period' => $period,
                'activity' => $activity,
                'review_status' => $attendance->journal_status ?: 'pending',
                'feedback' => $attendance->journal_feedback,
                'reviewed_by' => $attendance->journal_reviewed_by,
                'reviewed_at' => $attendance->journal_reviewed_at,
                'created_at' => $attendance->created_at,
                'updated_at' => $attendance->updated_at,
            ]);
        }
    }

    private function backfillReviews(object $attendance): void
    {
        if ($attendance->supervisor_review_status !== 'pending' || $attendance->supervisor_feedback
            || $attendance->supervisor_reviewed_by || $attendance->supervisor_reviewed_at) {
            DB::table('attendance_reviews')->insert([
                'attendance_id' => $attendance->id,
                'review_type' => 'supervisor',
                'decision' => $attendance->supervisor_review_status ?: 'pending',
                'feedback' => $attendance->supervisor_feedback,
                'reviewed_by' => $attendance->supervisor_reviewed_by,
                'reviewed_at' => $attendance->supervisor_reviewed_at,
                'created_at' => $attendance->created_at,
                'updated_at' => $attendance->updated_at,
            ]);
        }

        if ($attendance->is_verified || $attendance->verified_by || $attendance->verified_at) {
            DB::table('attendance_reviews')->insert([
                'attendance_id' => $attendance->id,
                'review_type' => 'verification',
                'decision' => $attendance->is_verified ? 'approved' : 'pending',
                'feedback' => null,
                'reviewed_by' => $attendance->verified_by,
                'reviewed_at' => $attendance->verified_at,
                'created_at' => $attendance->created_at,
                'updated_at' => $attendance->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_reviews');
        Schema::dropIfExists('attendance_journals');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_schedule_snapshots');
        Schema::table('attendance', fn (Blueprint $table) => $table->dropConstrainedForeignId('holiday_id'));
    }
};
