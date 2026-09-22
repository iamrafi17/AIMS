<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceScheduleSnapshot extends Model
{
    protected $fillable = [
        'attendance_id', 'period', 'is_working', 'scheduled_start', 'scheduled_end',
        'grace_period_minutes', 'source', 'hte_schedule_id', 'hte_batch_schedule_id',
        'student_schedule_override_id',
    ];

    protected function casts(): array
    {
        return ['is_working' => 'boolean', 'grace_period_minutes' => 'integer'];
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function batchSchedule()
    {
        return $this->belongsTo(HteBatchSchedule::class, 'hte_batch_schedule_id');
    }

    public function hteSchedule()
    {
        return $this->belongsTo(HteSchedule::class);
    }

    public function studentOverride()
    {
        return $this->belongsTo(StudentScheduleOverride::class, 'student_schedule_override_id');
    }
}
