<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class ReportTemplate extends Model
{
    use HasFactory, SoftDeletes;

    public const SCOPE_ALL_PERIODS = 'all_periods';

    public const SCOPE_ACADEMIC_PERIOD = 'academic_period';

    protected $attributes = [
        'period_scope' => self::SCOPE_ALL_PERIODS,
    ];

    protected $fillable = [
        'program_id', 'period_scope', 'report_type', 'name', 'original_filename', 'file_path',
        'mime_type', 'file_size', 'checksum', 'version', 'academic_period_id', 'description',
        'validation_status', 'validation_result', 'is_active', 'active_key', 'uploaded_by',
        'validated_at', 'activated_at', 'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'validation_result' => 'array',
            'is_active' => 'boolean',
            'validated_at' => 'datetime',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function generatedReports()
    {
        return $this->hasMany(GeneratedReport::class);
    }

    public function scopeForTemplateScope(
        $query,
        ?int $programId,
        string $reportType,
        string $periodScope,
        ?int $academicPeriodId = null,
    ) {
        return $query
            ->where('program_id', $programId)
            ->where('report_type', $reportType)
            ->where('period_scope', $periodScope)
            ->when(
                $periodScope === self::SCOPE_ACADEMIC_PERIOD,
                fn ($scope) => $scope->where('academic_period_id', $academicPeriodId),
                fn ($scope) => $scope->whereNull('academic_period_id'),
            );
    }

    public function activationKey(): string
    {
        return ($this->program_id ?: 'university').'|'.$this->report_type.'|'.$this->period_scope.'|'.($this->academic_period_id ?: 'all');
    }

    public function getAcademicYearAttribute(): string
    {
        return $this->period_scope === self::SCOPE_ACADEMIC_PERIOD
            ? ($this->academicPeriod?->academic_year ?? 'All Academic Years')
            : 'All Academic Years';
    }

    public function getSemesterAttribute(): string
    {
        return $this->period_scope === self::SCOPE_ACADEMIC_PERIOD
            ? ($this->academicPeriod?->legacySemester() ?? 'All Semesters')
            : 'All Semesters';
    }

    public function getDetectedPlaceholdersAttribute(): array
    {
        return array_values(data_get($this->validation_result, 'detected_placeholders', []));
    }

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            if ($template->period_scope === self::SCOPE_ALL_PERIODS && $template->academic_period_id !== null) {
                throw new LogicException('All-period report templates cannot reference an academic period.');
            }
            if ($template->period_scope === self::SCOPE_ACADEMIC_PERIOD && $template->academic_period_id === null) {
                throw new LogicException('Academic-period report templates must reference an academic period.');
            }
        });
    }
}
