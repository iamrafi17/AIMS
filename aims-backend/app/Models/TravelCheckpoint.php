<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelCheckpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'travel_log_id',
        'sequence',
        'checkpoint_name',
        'expected_at',
        'latitude',
        'longitude',
        'photo_path',
        'notes',
        'is_verified',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'expected_at' => 'datetime',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function travelLog()
    {
        return $this->belongsTo(TravelLog::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
