<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HTE extends Model
{
    use HasFactory;

    protected $table = 'htes';

    protected $fillable = [
        'college_id',
        'program_id',
        'name',
        'address',
        'contact_person',
        'contact_email',
        'contact_phone',
        'latitude',
        'longitude',
        'geofence_radius',
        'geofence_enabled',
        'default_am_start',
        'default_am_end',
        'default_pm_start',
        'default_pm_end',
        'work_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'geofence_radius' => 'integer',
            'geofence_enabled' => 'boolean',
            'work_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'hte_id');
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function colleges()
    {
        return $this->belongsToMany(College::class, 'moas', 'hte_id', 'college_id');
    }

    public function moas()
    {
        return $this->hasMany(MOA::class, 'hte_id');
    }
}
