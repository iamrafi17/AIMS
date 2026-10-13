<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'student_internship_id',
        'program_requirement_id',
        'requirement_name',
        'file_path',
        'status',
        'feedback',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $appends = ['file_type'];

    public function getFileTypeAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        $extension = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));

        return match (true) {
            $extension === 'pdf' => 'pdf',
            in_array($extension, ['jpg', 'jpeg', 'png'], true) => 'image',
            default => 'document',
        };
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InternshipRequirement $requirement): void {
            $requirement->syncStudentInternshipReference();
        });
    }

    private function syncStudentInternshipReference(): void
    {
        if (! $this->student_internship_id && $this->student_id) {
            $this->student_internship_id = StudentInternship::query()
                ->where('student_id', $this->student_id)
                ->where('is_current', true)
                ->latest('id')
                ->value('id');
        }

        if (! $this->student_id && $this->student_internship_id) {
            $this->student_id = StudentInternship::query()
                ->whereKey($this->student_internship_id)
                ->value('student_id');
        }

    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(StudentInternship::class, 'student_internship_id');
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

    public function isConsent(): bool
    {
        return str_contains(mb_strtolower($this->requirement_name), 'consent');
    }

    public function scopeActiveDefinitionOrLegacy(Builder $query): Builder
    {
        return $query->where(function (Builder $nested) {
            $nested->whereNull('program_requirement_id')
                ->orWhereHas('definition', fn (Builder $definition) => $definition->where('is_active', true));
        });
    }

    public function scopeForCurrentInternship(Builder $query): Builder
    {
        return $query->whereHas('internship', fn (Builder $internship) => $internship->where('is_current', true));
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
            ->with('currentInternship.academicSection:id,program_id')
            ->get(['id'])
            ->filter(fn (Student $student) => $student->currentInternship?->academicSection?->program_id)
            ->groupBy(fn (Student $student) => (int) $student->program_id);

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
                        ->where('student_internship_id', $student->currentInternship->id)
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
                        'student_internship_id' => $student->currentInternship->id,
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
            ->forCurrentInternship()
            ->whereHas('definition', fn ($query) => $query->where('is_active', true))
            ->join('program_requirements', 'program_requirements.id', '=', 'internship_requirements.program_requirement_id')
            ->orderBy('program_requirements.sort_order')
            ->orderBy('program_requirements.id')
            ->select('internship_requirements.*')
            ->get();
    }
}
