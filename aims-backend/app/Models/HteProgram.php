<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteProgram extends Model
{
    protected $fillable = [
        'hte_id',
        'program_id',
        'linked_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class, 'hte_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function linker()
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function placements()
    {
        return $this->hasMany(StudentInternshipPlacement::class, 'hte_program_id');
    }
}
