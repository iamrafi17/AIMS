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
}
