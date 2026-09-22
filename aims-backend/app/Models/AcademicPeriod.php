<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    public const SEMESTERS = ['1st Semester', '2nd Semester', 'Summer'];

    protected $fillable = ['academic_year', 'semester', 'start_date', 'end_date', 'status', 'completed_at'];

    protected $hidden = ['active_slot'];

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'completed_at' => 'datetime'];
    }

    public static function normalizeYear(string $year): string
    {
        return str_replace(['–', '—', ' '], ['-', '-', ''], trim($year));
    }

    public static function normalizeSemester(string $semester): string
    {
        return match (strtolower(trim($semester))) {
            'first semester', '1st semester' => '1st Semester',
            'second semester', '2nd semester' => '2nd Semester',
            'summer' => 'Summer',
            default => trim($semester),
        };
    }

    public function semesterAliases(): array
    {
        return match ($this->semester) {
            '1st Semester' => ['1st Semester', 'First Semester'],
            '2nd Semester' => ['2nd Semester', 'Second Semester'],
            default => [$this->semester],
        };
    }

    public function legacySemester(): string
    {
        return $this->semesterAliases()[1] ?? $this->semester;
    }

    public function enrollments()
    {
        return $this->hasManyThrough(
            OjtEnrollment::class,
            AcademicSection::class,
            'academic_period_id',
            'academic_section_id',
            'id',
            'id',
        );
    }

    public function sections()
    {
        return $this->hasMany(AcademicSection::class);
    }

    public function reportTemplates()
    {
        return $this->hasMany(ReportTemplate::class)->withTrashed();
    }

    public function generatedReports()
    {
        return $this->hasManyThrough(GeneratedReport::class, ReportTemplate::class)->withTrashedParents();
    }
}
