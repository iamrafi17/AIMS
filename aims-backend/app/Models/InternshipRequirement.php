<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'program_requirement_id',
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

    public function definition()
    {
        return $this->belongsTo(ProgramRequirement::class, 'program_requirement_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeActiveDefinitionOrLegacy(Builder $query): Builder
    {
        return $query->where(function (Builder $nested) {
            $nested->whereNull('program_requirement_id')
                ->orWhereHas('definition', fn (Builder $definition) => $definition->where('is_active', true));
        });
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

        $students = Student::query()
            ->whereIn('id', $studentIds)
            ->get(['id', 'program_id'])
            ->groupBy('program_id');

        foreach ($students as $programId => $programStudents) {
            ProgramRequirement::ensureDefaultsForProgram((int) $programId);
            $definitions = ProgramRequirement::query()
                ->where('program_id', $programId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($programStudents as $student) {
                foreach ($definitions as $definition) {
                    $requirement = static::query()
                        ->where('student_id', $student->id)
                        ->where(function ($query) use ($definition) {
                            $query->where('program_requirement_id', $definition->id)
                                ->orWhere(function ($legacy) use ($definition) {
                                    $legacy->whereNull('program_requirement_id')
                                        ->where('requirement_name', $definition->name);
                                });
                        })
                        ->first();

                    if ($requirement) {
                        if (! $requirement->program_requirement_id) {
                            $requirement->update(['program_requirement_id' => $definition->id]);
                        }

                        continue;
                    }

                    static::query()->create([
                        'student_id' => $student->id,
                        'program_requirement_id' => $definition->id,
                        'requirement_name' => $definition->name,
                        'status' => 'pending',
                    ]);
                }
            }
        }
    }

    public static function activeChecklistForStudent(int $studentId)
    {
        return static::query()
            ->with(['definition', 'reviewer'])
            ->where('student_id', $studentId)
            ->whereHas('definition', fn ($query) => $query->where('is_active', true))
            ->join('program_requirements', 'program_requirements.id', '=', 'internship_requirements.program_requirement_id')
            ->orderBy('program_requirements.sort_order')
            ->orderBy('program_requirements.id')
            ->select('internship_requirements.*')
            ->get();
    }
}
