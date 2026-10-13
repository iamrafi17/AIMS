<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramOjtSetting extends Model
{
    protected $fillable = [
        'program_id', 'academic_period_id', 'required_hours', 'ojt_start_date',
        'status', 'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'required_hours' => 'decimal:2',
            'ojt_start_date' => 'date:Y-m-d',
            'published_at' => 'datetime',
        ];
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public static function publishedFor(int $programId, int $periodId): ?self
    {
        return self::query()->where('program_id', $programId)
            ->where('academic_period_id', $periodId)
            ->where('status', 'published')
            ->first();
    }
}
