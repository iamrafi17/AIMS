<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OjtEnrollment extends Model
{
    protected $fillable = [
        'school_id',
        'full_name',
        'section',
        'college_id',
        'program_id',
        'source',
        'added_by',
        'student_record_id',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
        ];
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_record_id');
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
