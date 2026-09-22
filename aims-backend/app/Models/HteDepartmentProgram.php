<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteDepartmentProgram extends Model
{
    protected $fillable = [
        'hte_department_id',
        'program_id',
        'linked_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department()
    {
        return $this->belongsTo(HteDepartment::class, 'hte_department_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function placements()
    {
        return $this->hasMany(StudentInternshipPlacement::class, 'hte_department_program_id');
    }
}
