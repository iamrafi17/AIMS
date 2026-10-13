<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    public const TYPES = [
        'national_holiday',
        'local_holiday',
        'special_non_working',
    ];

    protected $fillable = [
        'college_id',
        'program_id',
        'name',
        'date',
        'holiday_type',
        'description',
        'official_reference',
        'source_url',
        'affected_area',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_recurring' => 'boolean',
        ];
    }

    public function htes()
    {
        return $this->belongsToMany(HTE::class, 'holiday_hte', 'holiday_id', 'hte_id')->withTimestamps();
    }
}
