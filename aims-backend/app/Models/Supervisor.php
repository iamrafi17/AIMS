<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A supervisor's assignment to an HTE department. Person details live in the profile. */
class Supervisor extends Model
{
    protected $table = 'hte_supervisor_assignments';

    protected $fillable = [
        'supervisor_profile_id', 'hte_department_id', 'is_active',
        'full_name', 'normalized_name', 'user_id',
    ];

    protected $appends = ['full_name', 'normalized_name', 'user_id', 'contact_email'];

    protected static function booted(): void
    {
        static::saving(function (Supervisor $assignment): void {
            $name = $assignment->attributes['full_name'] ?? null;
            $userId = $assignment->attributes['user_id'] ?? null;
            $hasUserId = array_key_exists('user_id', $assignment->attributes);

            unset($assignment->attributes['full_name'], $assignment->attributes['normalized_name'], $assignment->attributes['user_id']);

            if (! $assignment->supervisor_profile_id) {
                $profile = SupervisorProfile::create(['full_name' => $name, 'user_id' => $userId]);
                $assignment->supervisor_profile_id = $profile->id;
            } elseif ($name !== null || $hasUserId) {
                $profile = $assignment->supervisorProfile()->firstOrFail();
                $changes = [];
                if ($name !== null) {
                    $changes['full_name'] = $name;
                }
                if ($hasUserId) {
                    $changes['user_id'] = $userId;
                }
                $profile->update($changes);
                $assignment->unsetRelation('supervisorProfile');
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getFullNameAttribute(): ?string
    {
        return $this->supervisorProfile?->full_name;
    }

    public function getNormalizedNameAttribute(): ?string
    {
        $name = $this->full_name;

        return $name === null ? null : mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }

    public function getUserIdAttribute(): ?int
    {
        return $this->supervisorProfile?->user_id;
    }

    public function getContactEmailAttribute(): ?string
    {
        return $this->supervisorProfile?->contact_email ?? $this->supervisorProfile?->user?->email;
    }

    public function supervisorProfile()
    {
        return $this->belongsTo(SupervisorProfile::class);
    }

    public function normalizedProfile()
    {
        return $this->supervisorProfile();
    }

    public function department()
    {
        return $this->belongsTo(HteDepartment::class, 'hte_department_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function placements()
    {
        return $this->hasMany(StudentInternshipPlacement::class, 'supervisor_record_id');
    }
}
