<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelLog extends Model
{
    use HasFactory;

    protected $hidden = [
        'arrival_photo_path',
    ];

    protected $fillable = [
        'created_by',
        'hte_id',
        'session_code',
        'destination',
        'purpose',
        'route_notes',
        'scheduled_at',
        'start_time',
        'end_time',
        'arrived_at',
        'arrival_photo_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'arrived_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function checkpoints()
    {
        return $this->hasMany(TravelCheckpoint::class);
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class);
    }

    public function participants()
    {
        return $this->belongsToMany(Student::class, 'travel_participants')->withTimestamps();
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
