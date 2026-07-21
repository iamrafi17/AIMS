<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'session_code',
        'start_time',
        'end_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function checkpoints()
    {
        return $this->hasMany(TravelCheckpoint::class);
    }
}
