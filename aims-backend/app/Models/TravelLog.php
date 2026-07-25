<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'created_by',
        'session_code',
        'destination',
        'purpose',
        'route_notes',
        'scheduled_at',
        'start_time',
        'end_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'scheduled_at' => 'datetime',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function companions()
    {
        return $this->hasMany(TravelCompanion::class);
    }
}
