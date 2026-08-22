<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteBatchSchedule extends Model
{
    protected $fillable = [
        'hte_id',
        'academic_section_id',
        'day_of_week',
        'is_working',
        'am_start',
        'am_end',
        'pm_start',
        'pm_end',
    ];

    protected function casts(): array
    {
        return [
            'is_working' => 'boolean',
        ];
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class, 'hte_id');
    }

    public function academicSection()
    {
        return $this->belongsTo(AcademicSection::class);
    }
}
