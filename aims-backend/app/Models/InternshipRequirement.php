<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternshipRequirement extends Model
{
    use HasFactory;

    public const OFFICIAL_REQUIREMENTS = [
        'Birth Certificate',
        'Evaluation Checklist',
        'Medical Certificate',
        "Notarized Parent's/Guardian's Consent",
        "Parent's/Guardian's ID Card",
        'Notarized Training Contract',
        'Sample Accomplished Training Contract',
        'Medical Insurance ID',
        'Official Registration Form/Official List Enrolled Student (COR)',
        'Curriculum Vitae',
    ];

    protected $fillable = [
        'student_id',
        'requirement_name',
        'file_path',
        'file_type',
        'status',
        'feedback',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function ensureForStudent(int $studentId): void
    {
        static::ensureForStudents([$studentId]);
    }

    public static function ensureForStudents(iterable $studentIds): void
    {
        $studentIds = collect($studentIds)->filter()->unique()->values();
        if ($studentIds->isEmpty()) {
            return;
        }

        $now = now();
        $existing = static::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('requirement_name', self::OFFICIAL_REQUIREMENTS)
            ->get(['student_id', 'requirement_name'])
            ->mapWithKeys(fn ($requirement) => [$requirement->student_id.'|'.$requirement->requirement_name => true]);

        $rows = $studentIds
            ->flatMap(fn ($studentId) => collect(self::OFFICIAL_REQUIREMENTS)->map(fn ($name) => [
                'student_id' => $studentId,
                'requirement_name' => $name,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]))
            ->reject(fn ($row) => $existing->has($row['student_id'].'|'.$row['requirement_name']))
            ->values()
            ->all();

        if ($rows) {
            static::query()->insertOrIgnore($rows);
        }
    }
}
