<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteSchedule extends Model
{
    protected $fillable = [
        'hte_id',
        'day_of_week',
        'is_working',
        'grace_period_minutes',
        'am_start',
        'am_end',
        'pm_start',
        'pm_end',
    ];

    protected function casts(): array
    {
        return [
            'is_working' => 'boolean',
            'grace_period_minutes' => 'integer',
        ];
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class, 'hte_id');
    }

    public function attendanceSnapshots()
    {
        return $this->hasMany(AttendanceScheduleSnapshot::class);
    }
}
