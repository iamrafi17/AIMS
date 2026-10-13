<?php

namespace App\Models;

use App\Support\UserId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    private array $profileInput = [];

    private const PROFILE_INPUT_KEYS = [
        'name', 'contact_number', 'address', 'avatar', 'profile_completed_at',
        'requires_profile_setup', 'college_id', 'program_id',
    ];

    protected $appends = [
        'name', 'role', 'contact_number', 'address', 'avatar',
        'profile_completed_at', 'requires_profile_setup', 'college_id', 'program_id',
    ];

    protected $fillable = [
        'name',
        'email',
        'username',
        'role_id',
        'contact_number',
        'address',
        'password',
        'role',
        'college_id',
        'program_id',
        'avatar',
        'is_active',
        'requires_password_change',
        'requires_security_setup',
        'requires_profile_setup',
        'profile_completed_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'requires_password_change' => 'boolean',
            'requires_security_setup' => 'boolean',
        ];
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, self::PROFILE_INPUT_KEYS, true)) {
            $this->profileInput[$key] = $value;
            return $this;
        }

        if ($key === 'role') {
            $this->role_id = Role::query()->where('code', $value)->value('id');
            $this->unsetRelation('roleDefinition');
            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (! $user->role_id) {
                $user->role = 'student';
            }
        });

        static::creating(function (User $user): void {
            $role = $user->role;
            if (UserId::isStaffRole($role) && ! $user->username) {
                $user->username = UserId::generate($role);
            }
        });

        static::updating(function (User $user): void {
            if (! $user->isDirty('role_id')) {
                return;
            }

            $role = $user->role;
            if (! UserId::isStaffRole($role)) {
                $user->username = $role === 'student' ? $user->student?->student_id : null;

                return;
            }

            if (! UserId::belongsToRole($user->username, $role)) {
                $user->username = UserId::generate($role);
            }
        });

        static::saved(function (User $user): void {
            $user->syncNormalizedProfile();
        });
    }

    public function roleDefinition()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function employeeProfile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function normalizedSupervisorProfile()
    {
        return $this->hasOne(SupervisorProfile::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function supervisedStudents()
    {
        return $this->hasMany(Student::class, 'supervisor_id');
    }

    public function supervisorProfile()
    {
        return $this->hasOneThrough(
            Supervisor::class,
            SupervisorProfile::class,
            'user_id',
            'supervisor_profile_id',
            'id',
            'id',
        )->where('hte_supervisor_assignments.is_active', true);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function securityQuestions()
    {
        return $this->hasMany(UserSecurityQuestion::class);
    }

    public function uploadedReportTemplates()
    {
        return $this->hasMany(ReportTemplate::class, 'uploaded_by');
    }

    public function generatedReports()
    {
        return $this->hasMany(GeneratedReport::class, 'generated_by');
    }

    public function normalizedProfile(): Student|EmployeeProfile|SupervisorProfile|null
    {
        return match ($this->role) {
            'student' => $this->student,
            'supervisor' => $this->normalizedSupervisorProfile,
            'admin', 'coordinator', 'program_head', 'dean', 'vpaa' => $this->employeeProfile,
            default => null,
        };
    }

    public function getRoleAttribute(?string $value): ?string
    {
        return $this->roleDefinition?->code;
    }

    public function getNameAttribute(?string $value): ?string
    {
        return $this->profileInput['name'] ?? $this->normalizedProfile()?->getAttribute('full_name') ?? $value;
    }

    public function getContactNumberAttribute(?string $value): ?string
    {
        return $this->profileInput['contact_number'] ?? $this->normalizedProfile()?->getAttribute('contact_number') ?? $value;
    }

    public function getAddressAttribute(?string $value): ?string
    {
        return $this->profileInput['address'] ?? $this->normalizedProfile()?->getAttribute('address') ?? $value;
    }

    public function getAvatarAttribute(?string $value): ?string
    {
        return $this->profileInput['avatar'] ?? $this->normalizedProfile()?->getAttribute('profile_photo_path') ?? $value;
    }

    public function getProfileCompletedAtAttribute(mixed $value): mixed
    {
        return $this->profileInput['profile_completed_at'] ?? $this->normalizedProfile()?->getAttribute('profile_completed_at') ?? $value;
    }

    public function getRequiresProfileSetupAttribute(mixed $value): bool
    {
        if (array_key_exists('requires_profile_setup', $this->profileInput)) {
            return (bool) $this->profileInput['requires_profile_setup'];
        }

        if (! UserId::isStaffRole((string) $this->role)) {
            return false;
        }

        $profile = $this->normalizedProfile();

        return $profile
            ? $profile->getAttribute('profile_completed_at') === null
            : (bool) $value;
    }

    public function getProgramIdAttribute(mixed $value): ?int
    {
        return $this->profileInput['program_id'] ?? $this->normalizedProgramId();
    }

    public function getCollegeIdAttribute(mixed $value): ?int
    {
        return $this->profileInput['college_id'] ?? $this->normalizedCollegeId();
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereHas('roleDefinition', fn (Builder $roles) => $roles->where('code', $role));
    }

    public function scopeForRoles(Builder $query, array $roles): Builder
    {
        return $query->whereHas('roleDefinition', fn (Builder $roleQuery) => $roleQuery->whereIn('code', $roles));
    }

    public function scopeForAssignedProgram(Builder $query, int $programId): Builder
    {
        return $query->whereHas(
            'employeeProfile.activeProgramAssignment',
            fn (Builder $assignments) => $assignments->where('program_id', $programId),
        );
    }

    public function normalizedProgramId(): ?int
    {
        if (! in_array($this->role, ['coordinator', 'program_head'], true)) {
            return null;
        }

        $programId = $this->employeeProfile?->activeProgramAssignment?->program_id;

        return $programId ? (int) $programId : null;
    }

    public function normalizedCollegeId(): ?int
    {
        if ($this->role === 'dean') {
            $collegeId = $this->employeeProfile?->getAttribute('college_id');
            return $collegeId ? (int) $collegeId : null;
        }

        $collegeId = $this->employeeProfile?->activeProgramAssignment?->program?->college_id;

        return $collegeId ? (int) $collegeId : null;
    }

    private function syncNormalizedProfile(): void
    {
        $role = (string) $this->role;
        $profileData = [];
        foreach ([
            'contact_number' => 'contact_number',
            'address' => 'address',
            'avatar' => 'profile_photo_path',
            'profile_completed_at' => 'profile_completed_at',
        ] as $input => $column) {
            if (array_key_exists($input, $this->profileInput)) {
                $profileData[$column] = $this->profileInput[$input];
            }
        }

        if (in_array($role, ['admin', 'coordinator', 'program_head', 'dean', 'vpaa'], true)) {
            if (array_key_exists('name', $this->profileInput)) {
                $profileData += EmployeeProfile::splitFullName((string) $this->profileInput['name']);
            }
            if (! $this->employeeProfile()->exists()) {
                if (! isset($profileData['first_name'])) {
                    $profileData += EmployeeProfile::splitFullName($this->name ?? 'Staff');
                }
                $profileData['profile_completed_at'] ??= ($this->profileInput['requires_profile_setup'] ?? false)
                    ? null : ($this->created_at ?? now());
            }

            $profile = EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $this->id],
                $profileData,
            );

            if ($role === 'dean' && array_key_exists('college_id', $this->profileInput)) {
                $profile->programAssignments()->update(['is_active' => false]);
                $profile->update(['college_id' => $this->profileInput['college_id'], 'program_id' => null]);
            }

            if (array_key_exists('program_id', $this->profileInput)) {
                $programId = $this->profileInput['program_id'] ?? null;
                $profile->programAssignments()->update(['is_active' => false]);
                if (in_array($role, ['coordinator', 'program_head'], true) && $programId) {
                    $profile->programAssignments()->updateOrCreate(
                        ['program_id' => $programId],
                        ['is_active' => (bool) ($this->is_active ?? true)],
                    );
                }
                if ($role !== 'dean') {
                    EmployeeProfile::syncAcademicForeignKeysFor($profile->id);
                }
            }

            $this->unsetRelation('employeeProfile');
        }

        if ($role === 'student' && $this->student()->exists()) {
            $studentData = array_intersect_key($profileData, array_flip([
                'contact_number', 'address', 'profile_photo_path', 'profile_completed_at',
            ]));
            if ($studentData) {
                $this->student()->update($studentData);
            }
            $this->unsetRelation('student');
        }

        if ($role === 'supervisor') {
            $supervisor = Supervisor::query()
                ->whereHas('supervisorProfile', fn ($query) => $query->where('user_id', $this->id))
                ->where('is_active', true)
                ->first();
            if ($supervisor) {
                if (isset($this->profileInput['name'])) {
                    $name = (string) $this->profileInput['name'];
                    $supervisor->update([
                        'full_name' => $name,
                        'normalized_name' => mb_strtolower(preg_replace('/\s+/', ' ', trim($name))),
                    ]);
                }
                if ($profileData) {
                    SupervisorProfile::query()->where('user_id', $this->id)->update($profileData);
                }
                $this->unsetRelation('normalizedSupervisorProfile');
            }
        }

        $this->profileInput = [];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim((string) $this->avatar)), '/');
        if ($path === '') {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isStudent()
    {
        return $this->role === 'student';
    }

    public function isCoordinator()
    {
        return $this->role === 'coordinator';
    }

    public function isProgramHead()
    {
        return $this->role === 'program_head';
    }

    public function isVPAA()
    {
        return $this->role === 'vpaa';
    }

    public function isSupervisor()
    {
        return $this->role === 'supervisor';
    }

    public function requiresStaffSecuritySetup(): bool
    {
        return UserId::isStaffRole($this->role) && $this->requires_security_setup;
    }

    public function requiresStaffOnboarding(): bool
    {
        return UserId::isStaffRole($this->role) && $this->onboardingStep() !== null;
    }

    public function onboardingStep(): ?string
    {
        if (! UserId::isStaffRole($this->role)) {
            return null;
        }

        if ($this->requires_profile_setup) {
            return 'complete_profile';
        }

        if ($this->requires_security_setup) {
            return 'security_setup';
        }

        if ($this->requires_password_change) {
            return 'change_password';
        }

        return null;
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }

        return $this->role === $roles;
    }
}
