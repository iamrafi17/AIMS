<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeProgramAssignment extends Model
{
    protected static function booted(): void
    {
        static::saved(fn (EmployeeProgramAssignment $assignment) => EmployeeProfile::syncAcademicForeignKeysFor($assignment->employee_profile_id));
        static::deleted(fn (EmployeeProgramAssignment $assignment) => EmployeeProfile::syncAcademicForeignKeysFor($assignment->employee_profile_id));
    }

    protected $fillable = ['employee_profile_id', 'program_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function employeeProfile()
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
