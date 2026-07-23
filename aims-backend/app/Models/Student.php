<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id',
        'first_name',
        'last_name',
        'middle_name',
        'gender',
        'birth_date',
        'address',
        'phone',
        'college_id',
        'program_id',
        'year_level',
        'section',
        'parent_name',
        'parent_relationship',
        'parent_address',
        'parent_phone',
        'hte_id',
        'internship_semester',
        'internship_year',
        'internship_status',
        'registration_status',
        'registration_feedback',
        'registration_reviewed_by',
        'registration_reviewed_at',
        'consent_status',
        'schedule_status',
        'ojt_start_date',
        'ojt_end_date',
        'required_ojt_hours',
        'allow_past_attendance',
        'official_am_start',
        'official_am_end',
        'official_pm_start',
        'official_pm_end',
        'work_days',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'year_level' => 'integer',
            'ojt_start_date' => 'date',
            'ojt_end_date' => 'date',
            'required_ojt_hours' => 'decimal:2',
            'allow_past_attendance' => 'boolean',
            'registration_reviewed_at' => 'datetime',
            'work_days' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function requirements()
    {
        return $this->hasMany(InternshipRequirement::class);
    }

    public function travelLogs()
    {
        return $this->hasMany(TravelLog::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->middle_name.' '.$this->last_name;
    }
}
