<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicSection extends Model
{
    protected $fillable = [
        'program_id',
        'name',
        'year_level',
        'academic_year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
