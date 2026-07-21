<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'student_id',
        'date',
        'time_in',
        'time_out',
        'am_time_in',
        'am_time_out',
        'pm_time_in',
        'pm_time_out',
        'ot_start',
        'ot_end',
        'overtime_hours',
        'am_activity',
        'pm_activity',
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
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'time_in' => 'datetime',
            'time_out' => 'datetime',
            'am_time_in' => 'datetime',
            'am_time_out' => 'datetime',
            'pm_time_in' => 'datetime',
            'pm_time_out' => 'datetime',
            'ot_start' => 'datetime',
            'ot_end' => 'datetime',
            'overtime_hours' => 'decimal:2',
            'am_time_in_location' => 'array',
            'am_time_out_location' => 'array',
            'pm_time_in_location' => 'array',
            'pm_time_out_location' => 'array',
            'latitude_in' => 'decimal:8',
            'longitude_in' => 'decimal:8',
            'latitude_out' => 'decimal:8',
            'longitude_out' => 'decimal:8',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
