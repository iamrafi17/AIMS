<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternshipTask extends Model
{
    protected $fillable = [
        'student_id',
        'student_internship_id',
        'supervisor_id',
        'title',
        'description',
        'due_date',
        'priority',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InternshipTask $task): void {
            $task->syncStudentInternshipReference();
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
}
