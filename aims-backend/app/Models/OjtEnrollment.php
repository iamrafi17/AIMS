<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OjtEnrollment extends Model
{
    private const DERIVED_CONTEXT_ATTRIBUTES = [
        'section', 'college_id', 'program_id', 'academic_period_id',
    ];

    protected array $contextInput = [];

    protected $fillable = [
        'school_id',
        'full_name',
        'section',
        'college_id',
        'program_id',
        'academic_section_id',
        'academic_period_id',
        'source',
        'added_by',
        'student_record_id',
        'registration_status',
        'registration_feedback',
        'registration_reviewed_by',
        'registration_reviewed_at',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'registration_reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OjtEnrollment $enrollment): void {
            if (! $enrollment->academic_section_id) {
                throw new \LogicException('An OJT enrollment requires an academic section.');
            }

            $section = AcademicSection::with('program')->find($enrollment->academic_section_id);
            if (! $section) {
                throw new \LogicException('The selected academic section does not exist.');
            }

            $expected = [
                'program_id' => $section->program_id,
                'college_id' => $section->program?->college_id,
                'academic_period_id' => $section->academic_period_id,
            ];

            foreach ($expected as $key => $value) {
                if (array_key_exists($key, $enrollment->contextInput)
                    && $enrollment->contextInput[$key] !== null
                    && (int) $enrollment->contextInput[$key] !== (int) $value) {
                    throw new \LogicException("The enrollment {$key} does not match its academic section.");
                }
            }
        });

        static::saved(function (OjtEnrollment $enrollment): void {
            $enrollment->contextInput = [];
            foreach (['academicSection', 'program', 'academicPeriod'] as $relation) {
                $enrollment->unsetRelation($relation);
            }
        });
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, self::DERIVED_CONTEXT_ATTRIBUTES, true)) {
            $this->contextInput[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        if (in_array($key, self::DERIVED_CONTEXT_ATTRIBUTES, true)) {
            if (array_key_exists($key, $this->contextInput)) {
                return $this->contextInput[$key];
            }

            return $this->resolveEnrollmentContext($key);
        }

        return parent::getAttribute($key);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach (self::DERIVED_CONTEXT_ATTRIBUTES as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }

        return $attributes;
    }

    private function resolveEnrollmentContext(string $key): mixed
    {
        $section = $this->relationLoaded('academicSection')
            ? $this->academicSection
            : $this->academicSection()->with('program')->first();

        return match ($key) {
            'section' => $section?->name,
            'program_id' => $section?->program_id,
            'college_id' => $section?->program?->college_id,
            'academic_period_id' => $section?->academic_period_id,
        };
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_record_id');
    }

    public function getCollegeAttribute(): ?College
    {
        return $this->academicSection?->program?->college;
    }

    public function program()
    {
        return $this->hasOneThrough(
            Program::class,
            AcademicSection::class,
            'id',
            'id',
            'academic_section_id',
            'program_id',
        );
    }

    public function academicSection(): BelongsTo
    {
        return $this->belongsTo(AcademicSection::class);
    }

    public function academicPeriod()
    {
        return $this->hasOneThrough(
            AcademicPeriod::class,
            AcademicSection::class,
            'id',
            'id',
            'academic_section_id',
            'academic_period_id',
        );
    }

    public function registrationReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registration_reviewed_by');
    }

    public function internship()
    {
        return $this->hasOne(StudentInternship::class, 'ojt_enrollment_id');
    }

    public function scopeForProgram(Builder $query, int $programId): Builder
    {
        return $query->whereHas('academicSection', fn (Builder $section) => $section->where('program_id', $programId));
    }

    public function scopeForCollege(Builder $query, int $collegeId): Builder
    {
        return $query->whereHas('academicSection.program', fn (Builder $program) => $program->where('college_id', $collegeId));
    }

    public function scopeForAcademicPeriod(Builder $query, int $periodId): Builder
    {
        return $query->whereHas('academicSection', fn (Builder $section) => $section->where('academic_period_id', $periodId));
    }
}
