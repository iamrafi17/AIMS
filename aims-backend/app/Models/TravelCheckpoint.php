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
        'point_type',
        'is_final_destination',
        'captured_at',
        'captured_by',
        'latitude',
        'longitude',
        'readable_location',
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
            'captured_at' => 'datetime',
            'is_final_destination' => 'boolean',
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

    public function capturer()
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
