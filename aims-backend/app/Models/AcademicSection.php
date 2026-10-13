<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AcademicSection extends Model
{
    protected bool $hasAcademicYearInput = false;

    protected ?string $academicYearInput = null;

    protected $fillable = [
        'program_id',
        'name',
        'year_level',
        'academic_year',
        'academic_period_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AcademicSection $section): void {
            if ($section->isDirty('academic_period_id') || ! $section->hasAcademicYearInput) {
                return;
            }

            $year = AcademicPeriod::normalizeYear((string) $section->academicYearInput);
            $query = AcademicPeriod::query()->where('academic_year', $year);
            $active = (clone $query)->where('status', 'active')->first();

            if ($active) {
                $section->academic_period_id = $active->id;

                return;
            }

            $matches = $query->limit(2)->get();
            $section->academic_period_id = $matches->count() === 1 ? $matches->first()->id : null;
        });

        static::saved(function (AcademicSection $section): void {
            if ($section->wasChanged('program_id')) {
                StudentInternship::query()->where('academic_section_id', $section->id)
                    ->where('is_current', true)->pluck('student_id')
                    ->merge(OjtEnrollment::query()->where('academic_section_id', $section->id)->pluck('student_record_id'))
                    ->filter()->unique()
                    ->each(fn (int $studentId) => Student::syncAcademicForeignKeysFor($studentId));
            }

            $section->hasAcademicYearInput = false;
            $section->academicYearInput = null;
            $section->unsetRelation('academicPeriod');
        });
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'academic_year') {
            $this->hasAcademicYearInput = true;
            $this->academicYearInput = $value === null ? null : AcademicPeriod::normalizeYear((string) $value);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        if ($key === 'academic_year') {
            if ($this->hasAcademicYearInput) {
                return $this->academicYearInput;
            }

            $period = $this->relationLoaded('academicPeriod')
                ? $this->getRelation('academicPeriod')
                : $this->academicPeriod()->first();

            return $period?->academic_year;
        }

        return parent::getAttribute($key);
    }

    public function attributesToArray(): array
    {
        return [...parent::attributesToArray(), 'academic_year' => $this->academic_year];
    }

    public function scopeNewestPeriodFirst(Builder $query): Builder
    {
        return $query->orderByDesc(
            AcademicPeriod::query()
                ->select('start_date')
                ->whereColumn('academic_periods.id', 'academic_sections.academic_period_id')
                ->limit(1),
        );
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function hteBatchSchedules()
    {
        return $this->hasMany(HteBatchSchedule::class);
    }

    public function studentInternships()
    {
        return $this->hasMany(StudentInternship::class);
    }

    public function enrollments()
    {
        return $this->hasMany(OjtEnrollment::class);
    }
}
