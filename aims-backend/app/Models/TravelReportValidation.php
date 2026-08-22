<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelReportValidation extends Model
{
    protected $fillable = [
        'travel_report_id',
        'reviewer_id',
        'reviewer_role',
        'action',
        'reason',
        'comments',
        'reviewed_at',
        'report_version',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'report_version' => 'integer',
        ];
    }

    public function report()
    {
        return $this->belongsTo(TravelReport::class, 'travel_report_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
