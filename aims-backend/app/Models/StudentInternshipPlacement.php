<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class StudentInternshipPlacement extends Model
{
    protected $fillable = [
        'student_internship_id',
        'hte_program_id',
        'hte_department_program_id',
        'supervisor_record_id',
        'assigned_by',
        'assigned_at',
        'ended_at',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (StudentInternshipPlacement $placement): void {
            $hasHteProgram = filled($placement->hte_program_id);
            $hasDepartmentProgram = filled($placement->hte_department_program_id);

            if ($hasHteProgram === $hasDepartmentProgram) {
                throw new LogicException('A placement must reference exactly one HTE program or department-program link.');
            }

            $internship = $placement->internship()->with('academicSection')->first();
            $programId = $internship?->academicSection?->program_id;
            if (! $internship || ! $programId) {
                throw new LogicException('A placement requires an internship with an academic section.');
            }

            if ($hasHteProgram) {
                $link = $placement->hteProgram()->first();
                if (! $link || (int) $link->program_id !== (int) $programId) {
                    throw new LogicException('The selected HTE is not linked to the student program.');
                }
                if ($placement->supervisor_record_id) {
                    throw new LogicException('A supervisor can only be assigned through a department placement.');
                }
            }

            if ($hasDepartmentProgram) {
                $link = $placement->departmentProgram()->first();
                if (! $link || (int) $link->program_id !== (int) $programId) {
                    throw new LogicException('The selected department is not linked to the student program.');
                }

                if ($placement->supervisor_record_id) {
                    $supervisorDepartment = $placement->supervisor()->value('hte_department_id');
                    if ((int) $supervisorDepartment !== (int) $link->hte_department_id) {
                        throw new LogicException('The selected supervisor does not belong to the placement department.');
                    }
                }
            }

            $placement->assigned_at ??= now();
            if (! $placement->is_current) {
                $placement->ended_at ??= now();
            }
        });
    }

    public function internship()
    {
        return $this->belongsTo(StudentInternship::class, 'student_internship_id');
    }

    public function hteProgram()
    {
        return $this->belongsTo(HteProgram::class, 'hte_program_id');
    }

    public function departmentProgram()
    {
        return $this->belongsTo(HteDepartmentProgram::class, 'hte_department_program_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_record_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
