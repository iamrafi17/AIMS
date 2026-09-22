<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteDepartment extends Model
{
    protected $fillable = [
        'hte_id',
        'department_name',
        'normalized_name',
    ];

    public function hte()
    {
        return $this->belongsTo(HTE::class, 'hte_id');
    }

    public function supervisors()
    {
        return $this->hasMany(Supervisor::class, 'hte_department_id');
    }

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'hte_department_programs', 'hte_department_id', 'program_id')
            ->withPivot(['linked_by', 'is_active'])
            ->withTimestamps();
    }

    public function programLinks()
    {
        return $this->hasMany(HteDepartmentProgram::class, 'hte_department_id');
    }

    public function placements()
    {
        return $this->hasManyThrough(
            StudentInternshipPlacement::class,
            HteDepartmentProgram::class,
            'hte_department_id',
            'hte_department_program_id',
            'id',
            'id',
        );
    }
}
