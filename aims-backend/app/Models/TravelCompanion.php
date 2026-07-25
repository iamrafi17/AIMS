<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelCompanion extends Model
{
    protected $fillable = ['travel_log_id', 'name', 'type', 'contact', 'relationship'];

    public function travelLog()
    {
        return $this->belongsTo(TravelLog::class);
    }
}
