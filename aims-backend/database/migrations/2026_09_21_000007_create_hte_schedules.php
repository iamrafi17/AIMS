<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function up(): void
    {
        Schema::create('hte_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hte_id')->constrained('htes')->cascadeOnDelete();
            $table->string('day_of_week', 3);
            $table->boolean('is_working');
            $table->unsignedSmallInteger('grace_period_minutes')->default(10);
            $table->time('am_start')->nullable();
            $table->time('am_end')->nullable();
            $table->time('pm_start')->nullable();
            $table->time('pm_end')->nullable();
            $table->timestamps();

            $table->unique(['hte_id', 'day_of_week'], 'hte_schedule_day_unique');
        });

        Schema::table('attendance_schedule_snapshots', function (Blueprint $table) {
            $table->foreignId('hte_schedule_id')->nullable()->after('source');
            $table->foreign('hte_schedule_id', 'attendance_snapshot_hte_schedule_fk')
                ->references('id')->on('hte_schedules')->nullOnDelete();
        });

        $this->backfillHteSchedules();
        $this->connectAttendanceSnapshots();
    }

    private function backfillHteSchedules(): void
    {
        DB::table('htes')->orderBy('id')->get()->each(function (object $hte): void {
            $workingDays = is_string($hte->work_days)
                ? json_decode($hte->work_days, true)
                : $hte->work_days;

            if (! is_array($workingDays) || $workingDays === []) {
                return;
            }

            foreach (self::WEEKDAYS as $day) {
                $isWorking = in_array($day, $workingDays, true);
                DB::table('hte_schedules')->insert([
                    'hte_id' => $hte->id,
                    'day_of_week' => $day,
                    'is_working' => $isWorking,
                    'grace_period_minutes' => 10,
                    'am_start' => $isWorking ? $hte->default_am_start : null,
                    'am_end' => $isWorking ? $hte->default_am_end : null,
                    'pm_start' => $isWorking ? $hte->default_pm_start : null,
                    'pm_end' => $isWorking ? $hte->default_pm_end : null,
                    'created_at' => $hte->created_at,
                    'updated_at' => $hte->updated_at,
                ]);
            }
        });
    }

    private function connectAttendanceSnapshots(): void
    {
        DB::table('attendance_schedule_snapshots as snapshots')
            ->join('attendance', 'attendance.id', '=', 'snapshots.attendance_id')
            ->join('student_internships', 'student_internships.id', '=', 'attendance.student_internship_id')
            ->where('snapshots.source', 'hte_default')
            ->whereNotNull('student_internships.hte_id')
            ->select([
                'snapshots.id', 'attendance.date', 'student_internships.hte_id',
            ])
            ->orderBy('snapshots.id')
            ->get()
            ->each(function (object $snapshot): void {
                $day = strtolower(date('D', strtotime($snapshot->date)));
                $scheduleId = DB::table('hte_schedules')
                    ->where('hte_id', $snapshot->hte_id)
                    ->where('day_of_week', $day)
                    ->value('id');

                if ($scheduleId) {
                    DB::table('attendance_schedule_snapshots')->where('id', $snapshot->id)->update([
                        'hte_schedule_id' => $scheduleId,
                        'source' => 'hte_schedule',
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('attendance_schedule_snapshots', function (Blueprint $table) {
            $table->dropForeign('attendance_snapshot_hte_schedule_fk');
            $table->dropColumn('hte_schedule_id');
        });
        Schema::dropIfExists('hte_schedules');
    }
};
