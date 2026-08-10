<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelParticipant extends Model
{
    protected $fillable = [
        'travel_log_id',
        'student_id',
    ];

    public function travelLog(): BelongsTo
    {
        return $this->belongsTo(TravelLog::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
