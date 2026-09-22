<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'college_id',
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function enrollments()
    {
        return $this->hasManyThrough(
            OjtEnrollment::class,
            AcademicSection::class,
            'program_id',
            'academic_section_id',
            'id',
            'id',
        );
    }

    public function htes()
    {
        return $this->belongsToMany(HTE::class, 'hte_programs', 'program_id', 'hte_id')
            ->withPivot(['linked_by', 'is_active'])
            ->withTimestamps();
    }

    public function hteDepartments()
    {
        return $this->belongsToMany(HteDepartment::class, 'hte_department_programs', 'program_id', 'hte_department_id')
            ->withPivot(['linked_by', 'is_active'])
            ->withTimestamps();
    }

    public function requirements()
    {
        return $this->hasMany(ProgramRequirement::class);
    }

    public function reportTemplates()
    {
        return $this->hasMany(ReportTemplate::class);
    }
}
