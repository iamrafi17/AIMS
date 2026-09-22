<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'student_internship_id',
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

    protected static function booted(): void
    {
        static::saving(function (Evaluation $evaluation): void {
            $evaluation->syncStudentInternshipReference();
        });
    }

    private function syncStudentInternshipReference(): void
    {
        if (! $this->student_internship_id && $this->student_id) {
            $this->student_internship_id = StudentInternship::query()
                ->where('student_id', $this->student_id)
                ->where('is_current', true)
                ->latest('id')
                ->value('id');
        }

        if (! $this->student_id && $this->student_internship_id) {
            $this->student_id = StudentInternship::query()
                ->whereKey($this->student_internship_id)
                ->value('student_id');
        }
    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(StudentInternship::class, 'student_internship_id');
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
