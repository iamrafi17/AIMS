<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'supervisor_id',
        'evaluation_type',
        'work_quality',
        'communication',
        'professionalism',
        'attendance',
        'technical_skills',
        'teamwork',
        'recommendations',
        'feedback',
        'status',
        'submitted_at',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function getAverageRatingAttribute()
    {
        return ($this->work_quality + $this->communication + $this->professionalism + 
                $this->attendance + $this->technical_skills + $this->teamwork) / 6;
    }
}
