<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelReport extends Model
{
    use HasFactory;

    public const EDITABLE_STATUSES = ['draft'];

    protected $fillable = [
        'travel_log_id',
        'coordinator_id',
        'report_number',
        'remarks',
        'status',
        'version',
        'generated_at',
        'submitted_at',
        'program_head_validated_at',
        'forwarded_at',
        'final_validated_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'generated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'program_head_validated_at' => 'datetime',
            'forwarded_at' => 'datetime',
            'final_validated_at' => 'datetime',
        ];
    }

    public function travelLog()
    {
        return $this->belongsTo(TravelLog::class);
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function validations()
    {
        return $this->hasMany(TravelReportValidation::class)->orderBy('reviewed_at');
    }
}
