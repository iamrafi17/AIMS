<?php

namespace App\Models;

use App\Support\StaffId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'staff_id',
        'phone',
        'address',
        'password',
        'role',
        'college_id',
        'program_id',
        'avatar',
        'is_active',
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
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (StaffId::isStaffRole($user->role) && ! $user->staff_id) {
                $user->staff_id = StaffId::generate($user->role);
            }
        });

        static::updating(function (User $user): void {
            if (! $user->isDirty('role')) {
                return;
            }

            if (! StaffId::isStaffRole($user->role)) {
                $user->staff_id = null;

                return;
            }

            if (! StaffId::belongsToRole($user->staff_id, $user->role)) {
                $user->staff_id = StaffId::generate($user->role);
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

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar
            ? url(Storage::disk('public')->url($this->avatar))
            : null;
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

    public function hasRole($roles)
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }

        return $this->role === $roles;
    }
}
