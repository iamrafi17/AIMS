<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'program_id', 'report_type', 'name', 'original_filename', 'file_path',
        'mime_type', 'file_size', 'checksum', 'version', 'academic_year',
        'semester', 'description', 'validation_status', 'validation_result',
        'detected_placeholders', 'is_active', 'active_key', 'uploaded_by',
        'validated_at', 'activated_at', 'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'validation_result' => 'array',
            'detected_placeholders' => 'array',
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

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function generatedReports()
    {
        return $this->hasMany(GeneratedReport::class);
    }

    public function scopeForPeriod($query, ?int $programId, string $reportType, string $academicYear, string $semester)
    {
        return $query
            ->where('program_id', $programId)
            ->where('report_type', $reportType)
            ->where('academic_year', $academicYear)
            ->where('semester', $semester);
    }

    public function activationKey(): string
    {
        return ($this->program_id ?: 'university').'|'.$this->report_type.'|'.$this->academic_year.'|'.$this->semester;
    }
}
