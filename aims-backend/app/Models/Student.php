<?php

namespace App\Models;

use App\Support\StudentRecordNormalizer;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Student extends Model
{
    use HasFactory;

    private const DERIVED_CONTEXT_ATTRIBUTES = [
        'college_id', 'program_id', 'academic_section_id', 'academic_period_id',
        'year_level', 'section',
        'parent_name', 'parent_relationship', 'parent_address', 'parent_contact_number',
        'hte_id', 'hte_department_id', 'supervisor_record_id', 'supervisor_id',
        'internship_semester', 'internship_year', 'internship_status',
        'registration_status', 'registration_feedback', 'registration_reviewed_by', 'registration_reviewed_at',
        'consent_status', 'schedule_status', 'ojt_start_date', 'ojt_end_date',
        'required_ojt_hours', 'allow_past_attendance',
        'official_am_start', 'official_am_end', 'official_pm_start', 'official_pm_end', 'work_days',
    ];

    protected array $contextInput = [];

    protected array $contextCache = [];

    public bool $syncScheduleOverrides = false;

    public ?int $placementAssignedBy = null;

    protected $fillable = [
        'user_id',
        'student_id',
        'first_name',
        'last_name',
        'middle_name',
        'gender',
        'birth_date',
        'address',
        'contact_number',
        'college_id',
        'program_id',
        'academic_section_id',
        'academic_period_id',
        'year_level',
        'section',
        'parent_name',
        'parent_relationship',
        'parent_address',
        'parent_contact_number',
        'hte_id',
        'hte_department_id',
        'supervisor_record_id',
        'supervisor_id',
        'internship_semester',
        'internship_year',
        'internship_status',
        'registration_status',
        'registration_feedback',
        'registration_reviewed_by',
        'registration_reviewed_at',
        'consent_status',
        'schedule_status',
        'ojt_start_date',
        'ojt_end_date',
        'required_ojt_hours',
        'allow_past_attendance',
        'official_am_start',
        'official_am_end',
        'official_pm_start',
        'official_pm_end',
        'work_days',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Student $student): void {
            StudentRecordNormalizer::sync($student);

            self::syncAcademicForeignKeysFor($student->id);

            User::query()->forRole('student')->whereKey($student->user_id)
                ->update(['username' => $student->student_id]);
            $student->unsetRelation('user');
        });
    }

    public static function syncAcademicForeignKeysFor(int $studentId): void
    {
        if (! Schema::hasColumns('students', ['college_id', 'program_id'])) {
            return;
        }

        $sectionId = StudentInternship::query()
            ->where('student_id', $studentId)
            ->where('is_current', true)
            ->latest('id')
            ->value('academic_section_id');

        $sectionId ??= OjtEnrollment::query()
            ->where('student_record_id', $studentId)
            ->latest('registered_at')
            ->latest('id')
            ->value('academic_section_id');

        $program = $sectionId ? DB::table('academic_sections')
            ->join('programs', 'programs.id', '=', 'academic_sections.program_id')
            ->where('academic_sections.id', $sectionId)
            ->first(['programs.id', 'programs.college_id']) : null;

        DB::table('students')->where('id', $studentId)->update([
            'program_id' => $program?->id,
            'college_id' => $program?->college_id,
        ]);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach (self::DERIVED_CONTEXT_ATTRIBUTES as $key) {
            $value = $this->getAttribute($key);
            $attributes[$key] = $value instanceof DateTimeInterface
                ? $this->serializeDate($value)
                : $value;
        }

        return $attributes;
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
                return $this->castContextInput($key, $this->contextInput[$key]);
            }

            return $this->resolveContextAttribute($key);
        }

        return parent::getAttribute($key);
    }

    public function hasNormalizedContextInput(array $keys = []): bool
    {
        if ($keys === []) {
            return $this->contextInput !== [];
        }

        return collect($keys)->contains(fn (string $key) => array_key_exists($key, $this->contextInput));
    }

    public function clearNormalizedContextInput(): void
    {
        $this->contextInput = [];
        $this->contextCache = [];
        $this->unsetRelation('guardian');
        $this->unsetRelation('ojtEnrollments');
        $this->unsetRelation('currentInternship');
    }

    private function resolveContextAttribute(string $key): mixed
    {
        $guardian = fn () => $this->contextRecord('guardian', fn () => $this->guardian()->first());
        $internship = fn () => $this->contextRecord(
            'internship',
            fn () => $this->currentInternship()->with([
                'academicSection.program',
                'academicSection.academicPeriod',
                'enrollment.academicPeriod',
                'currentPlacement.hteProgram',
                'currentPlacement.departmentProgram.department',
                'currentPlacement.supervisor',
                'hte.schedules',
                'hteDepartment',
                'supervisorRecord',
                'scheduleOverrides',
            ])->first(),
        );
        $enrollment = fn () => $this->contextRecord(
            'enrollment',
            fn () => $internship()?->enrollment
                ?: $this->ojtEnrollments()->latest('registered_at')->latest('id')->first(),
        );
        $section = fn () => $internship()?->academicSection;
        $period = fn () => $section()?->academicPeriod ?: $enrollment()?->academicPeriod;
        $program = fn () => $section()?->program;
        $schedule = fn () => $this->scheduleContext($internship());

        return match ($key) {
            'parent_name' => $guardian()?->full_name,
            'parent_relationship' => $guardian()?->relationship,
            'parent_address' => $guardian()?->address,
            'parent_contact_number' => $guardian()?->contact_number,

            'academic_section_id' => $section()?->id ?: $enrollment()?->academic_section_id,
            'academic_period_id' => $period()?->id,
            'program_id' => $program()?->id ?: $enrollment()?->program_id,
            'college_id' => $program()?->college_id ?: $enrollment()?->college_id,
            'year_level' => $section()?->year_level,
            'section' => $section()?->name ?: $enrollment()?->section,
            'internship_year' => $period()?->academic_year,
            'internship_semester' => $period()?->legacySemester(),

            'hte_id' => $internship()?->hte_id,
            'hte_department_id' => $internship()?->hte_department_id,
            'supervisor_record_id' => $internship()?->supervisor_record_id,
            'supervisor_id' => $internship()?->supervisorRecord?->user_id,
            'internship_status' => $internship()?->status ?? 'pending',
            'consent_status' => $internship()?->consent_status ?? 'pending',
            'schedule_status' => $internship()?->deployment_status ?? 'pending',
            'ojt_start_date' => $internship()?->start_date,
            'ojt_end_date' => $internship()?->end_date,
            'required_ojt_hours' => $internship()?->required_hours ?? '486.00',
            'allow_past_attendance' => (bool) ($internship()?->allow_past_attendance ?? false),

            'registration_status' => $enrollment()?->registration_status ?? 'pending',
            'registration_feedback' => $enrollment()?->registration_feedback,
            'registration_reviewed_by' => $enrollment()?->registration_reviewed_by,
            'registration_reviewed_at' => $enrollment()?->registration_reviewed_at,

            'official_am_start' => $schedule()['am_start'],
            'official_am_end' => $schedule()['am_end'],
            'official_pm_start' => $schedule()['pm_start'],
            'official_pm_end' => $schedule()['pm_end'],
            'work_days' => $schedule()['work_days'],
            default => null,
        };
    }

    private function contextRecord(string $key, callable $resolver): mixed
    {
        if (! array_key_exists($key, $this->contextCache)) {
            $this->contextCache[$key] = $resolver();
        }

        return $this->contextCache[$key];
    }

    private function scheduleContext(?StudentInternship $internship): array
    {
        return $this->contextRecord('schedule', function () use ($internship): array {
            if (! $internship) {
                return $this->emptyScheduleContext();
            }

            $rows = $internship->relationLoaded('scheduleOverrides')
                ? $internship->scheduleOverrides
                : $internship->scheduleOverrides()->get();

            if ($rows->isEmpty() && $internship->hte_id && $internship->academic_section_id) {
                $rows = HteBatchSchedule::query()
                    ->where('hte_id', $internship->hte_id)
                    ->where('academic_section_id', $internship->academic_section_id)
                    ->get();
            }

            if ($rows->isNotEmpty()) {
                $working = $rows->where('is_working', true);
                $hours = $working->first();

                return [
                    'work_days' => $this->orderedWorkDays($working),
                    'am_start' => $hours?->am_start,
                    'am_end' => $hours?->am_end,
                    'pm_start' => $hours?->pm_start,
                    'pm_end' => $hours?->pm_end,
                ];
            }

            $hteRows = $internship->hte_id
                ? HteSchedule::query()->where('hte_id', $internship->hte_id)->get()
                : collect();

            if ($hteRows->isEmpty()) {
                return $this->emptyScheduleContext();
            }

            $working = $hteRows->where('is_working', true);
            $hours = $working->first();

            return [
                'work_days' => $this->orderedWorkDays($working),
                'am_start' => $hours?->am_start,
                'am_end' => $hours?->am_end,
                'pm_start' => $hours?->pm_start,
                'pm_end' => $hours?->pm_end,
            ];
        });
    }

    private function orderedWorkDays(Collection $rows): array
    {
        return collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
            ->filter(fn (string $day) => $rows->contains('day_of_week', $day))
            ->values()
            ->all();
    }

    private function emptyScheduleContext(): array
    {
        return [
            'work_days' => [],
            'am_start' => null,
            'am_end' => null,
            'pm_start' => null,
            'pm_end' => null,
        ];
    }

    private function castContextInput(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($key) {
            'year_level' => (int) $value,
            'allow_past_attendance' => (bool) $value,
            'required_ojt_hours' => number_format((float) $value, 2, '.', ''),
            'work_days' => is_array($value) ? $value : json_decode((string) $value, true),
            'ojt_start_date', 'ojt_end_date', 'registration_reviewed_at' => $this->asDateTime($value),
            default => $value,
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function academicSection()
    {
        return $this->belongsTo(AcademicSection::class);
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function hteDepartment()
    {
        return $this->belongsTo(HteDepartment::class, 'hte_department_id');
    }

    public function supervisorRecord()
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_record_id');
    }

    public function guardian()
    {
        return $this->hasOne(StudentGuardian::class);
    }

    public function internships()
    {
        return $this->hasMany(StudentInternship::class);
    }

    public function ojtEnrollments()
    {
        return $this->hasMany(OjtEnrollment::class, 'student_record_id');
    }

    public function currentInternship()
    {
        return $this->hasOne(StudentInternship::class)->where('is_current', true)->latestOfMany();
    }

    public function scopeForProgram(Builder $query, int $programId): Builder
    {
        return $query->whereHas('currentInternship.academicSection', fn (Builder $section) => $section->where('program_id', $programId));
    }

    public function scopeForCollege(Builder $query, int $collegeId): Builder
    {
        return $query->whereHas('currentInternship.academicSection.program', fn (Builder $program) => $program->where('college_id', $collegeId));
    }

    public function scopeForAcademicSection(Builder $query, int $sectionId): Builder
    {
        return $query->whereHas('currentInternship', fn (Builder $internships) => $internships->where('academic_section_id', $sectionId));
    }

    public function scopeForAcademicYear(Builder $query, string $year): Builder
    {
        return $query->whereHas('currentInternship.academicSection.academicPeriod', fn (Builder $period) => $period->where('academic_year', $year));
    }

    public function scopeForSemester(Builder $query, string $semester): Builder
    {
        $normalized = AcademicPeriod::normalizeSemester($semester);

        return $query->whereHas('currentInternship.academicSection.academicPeriod', fn (Builder $period) => $period->where('semester', $normalized));
    }

    public function scopeForHte(Builder $query, int $hteId): Builder
    {
        return $query->whereHas('currentInternship.currentPlacement', function (Builder $placements) use ($hteId): void {
            $placements->where(function (Builder $targets) use ($hteId): void {
                $targets->whereHas('hteProgram', fn (Builder $links) => $links->where('hte_id', $hteId))
                    ->orWhereHas(
                        'departmentProgram.department',
                        fn (Builder $departments) => $departments->where('hte_id', $hteId),
                    );
            });
        });
    }

    public function scopeForInternshipStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('currentInternship', fn (Builder $internships) => $internships->where('status', $status));
    }

    public function scopeForSupervisorUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas(
            'currentInternship.currentPlacement.supervisor',
            fn (Builder $supervisors) => $supervisors->whereHas(
                'supervisorProfile', fn (Builder $profiles) => $profiles->where('user_id', $userId),
            ),
        );
    }

    public function scopeDeployed(Builder $query): Builder
    {
        return $query->whereHas('currentInternship.currentPlacement');
    }

    public function tasks()
    {
        return $this->hasMany(InternshipTask::class);
    }

    public function attendance()
    {
        return $this->hasManyThrough(
            Attendance::class,
            StudentInternship::class,
            'student_id',
            'student_internship_id',
            'id',
            'id',
        );
    }

    public function liveLocation()
    {
        return $this->hasOne(StudentLiveLocation::class);
    }

    public function requirements()
    {
        return $this->hasMany(InternshipRequirement::class);
    }

    public function travelLogs()
    {
        return $this->belongsToMany(TravelLog::class, 'travel_participants')->withTimestamps();
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function getFullNameAttribute()
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter(fn ($part) => filled($part))
            ->map(fn ($part) => trim((string) $part))
            ->implode(' ');
    }
}
