<?php

namespace App\Models;

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
        'phone',
        'address',
        'password',
        'role',
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

    public function student()
    {
        return $this->hasOne(Student::class);
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
