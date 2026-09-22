<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class StudentInternship extends Model
{
    private const PLACEMENT_ATTRIBUTES = [
        'hte_program_id',
        'hte_id',
        'hte_department_id',
        'hte_department_program_id',
        'supervisor_record_id',
    ];

    protected array $placementContextCache = [];

    protected $fillable = [
        'student_id',
        'ojt_enrollment_id',
        'academic_section_id',
        'status',
        'consent_status',
        'deployment_status',
        'start_date',
        'end_date',
        'required_hours',
        'allow_past_attendance',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'required_hours' => 'decimal:2',
            'allow_past_attendance' => 'boolean',
            'is_current' => 'boolean',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(OjtEnrollment::class, 'ojt_enrollment_id');
    }

    public function academicSection()
    {
        return $this->belongsTo(AcademicSection::class);
    }

    public function placements()
    {
        return $this->hasMany(StudentInternshipPlacement::class, 'student_internship_id');
    }

    public function currentPlacement()
    {
        return $this->hasOne(StudentInternshipPlacement::class, 'student_internship_id')
            ->where('is_current', true);
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class, 'hte_id');
    }

    public function hteProgram()
    {
        return $this->belongsTo(HteProgram::class, 'hte_program_id');
    }

    public function hteDepartment()
    {
        return $this->belongsTo(HteDepartment::class, 'hte_department_id');
    }

    public function departmentProgram()
    {
        return $this->belongsTo(HteDepartmentProgram::class, 'hte_department_program_id');
    }

    public function supervisorRecord()
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_record_id');
    }

    public function scheduleOverrides()
    {
        return $this->hasMany(StudentScheduleOverride::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'student_internship_id');
    }

    public function requirements()
    {
        return $this->hasMany(InternshipRequirement::class, 'student_internship_id');
    }

    public function tasks()
    {
        return $this->hasMany(InternshipTask::class, 'student_internship_id');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'student_internship_id');
    }

    public function getAttribute($key)
    {
        if (in_array($key, self::PLACEMENT_ATTRIBUTES, true)) {
            return $this->resolvePlacementContext($key);
        }

        return parent::getAttribute($key);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach (self::PLACEMENT_ATTRIBUTES as $key) {
            $value = $this->getAttribute($key);
            $attributes[$key] = $value instanceof DateTimeInterface
                ? $this->serializeDate($value)
                : $value;
        }

        return $attributes;
    }

    public function clearPlacementContext(): void
    {
        $this->placementContextCache = [];
        $this->unsetRelation('currentPlacement');
    }

    private function resolvePlacementContext(string $key): mixed
    {
        if (! array_key_exists('values', $this->placementContextCache)) {
            $placement = $this->relationLoaded('currentPlacement')
                ? $this->getRelation('currentPlacement')
                : $this->currentPlacement()
                    ->with(['hteProgram', 'departmentProgram.department', 'supervisor'])
                    ->first();

            $departmentProgram = $placement?->departmentProgram;
            $department = $departmentProgram?->department;
            $programId = $departmentProgram?->program_id ?: $placement?->hteProgram?->program_id;
            $hteId = $department?->hte_id ?: $placement?->hteProgram?->hte_id;
            $hteProgramId = $placement?->hte_program_id;

            if (! $hteProgramId && $hteId && $programId) {
                $hteProgramId = HteProgram::query()
                    ->where('hte_id', $hteId)
                    ->where('program_id', $programId)
                    ->value('id');
            }

            $this->placementContextCache['values'] = [
                'hte_program_id' => $hteProgramId,
                'hte_id' => $hteId,
                'hte_department_id' => $department?->id,
                'hte_department_program_id' => $departmentProgram?->id,
                'supervisor_record_id' => $placement?->supervisor_record_id,
            ];
        }

        return $this->placementContextCache['values'][$key] ?? null;
    }
}
