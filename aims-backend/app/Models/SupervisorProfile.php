<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorProfile extends Model
{
    protected $fillable = [
        'user_id', 'full_name', 'contact_number', 'address',
        'profile_photo_path', 'profile_completed_at',
    ];

    protected function casts(): array
    {
        return ['profile_completed_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(HteSupervisorAssignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(HteSupervisorAssignment::class)->where('is_active', true)->latestOfMany();
    }
}
