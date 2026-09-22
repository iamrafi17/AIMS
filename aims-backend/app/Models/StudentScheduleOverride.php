<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentScheduleOverride extends Model
{
    protected $fillable = [
        'student_internship_id',
        'day_of_week',
        'is_working',
        'am_start',
        'am_end',
        'pm_start',
        'pm_end',
        'reason',
        'source',
        'approved_by',
    ];

    protected function casts(): array
    {
        return ['is_working' => 'boolean'];
    }

    public function internship()
    {
        return $this->belongsTo(StudentInternship::class, 'student_internship_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
