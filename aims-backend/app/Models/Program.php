<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Program extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updated(function (Program $program): void {
            if (! $program->wasChanged('college_id')) {
                return;
            }

            if (Schema::hasColumn('students', 'college_id')) {
                DB::table('students')->where('program_id', $program->id)
                    ->update(['college_id' => $program->college_id]);
            }
            if (Schema::hasColumn('employee_profiles', 'college_id')) {
                DB::table('employee_profiles')->where('program_id', $program->id)
                    ->update(['college_id' => $program->college_id]);
            }
        });
    }

    protected $fillable = [
        'college_id',
        'name',
        'code',
        'is_active',
        'smart_clock_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'smart_clock_enabled' => 'boolean',
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
