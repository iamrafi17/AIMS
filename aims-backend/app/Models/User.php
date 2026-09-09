<?php

namespace App\Models;

use App\Support\UserId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'user_id',
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
            'requires_profile_setup' => 'boolean',
            'profile_completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (UserId::isStaffRole($user->role) && ! $user->user_id) {
                $user->user_id = UserId::generate($user->role);
            }
        });

        static::updating(function (User $user): void {
            if (! $user->isDirty('role')) {
                return;
            }

            if (! UserId::isStaffRole($user->role)) {
                $user->user_id = null;

                return;
            }

            if (! UserId::belongsToRole($user->user_id, $user->role)) {
                $user->user_id = UserId::generate($user->role);
            }
        });
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
        return $this->hasOne(Supervisor::class, 'user_id');
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

    public function hasRole($roles)
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }

        return $this->role === $roles;
    }
}
