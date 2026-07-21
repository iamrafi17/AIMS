<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HTE extends Model
{
    use HasFactory;

    protected $table = 'htes';

    protected $fillable = [
        'name',
        'address',
        'contact_person',
        'contact_email',
        'contact_phone',
        'latitude',
        'longitude',
        'geofence_radius',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'geofence_radius' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function colleges()
    {
        return $this->belongsToMany(College::class, 'moas', 'hte_id', 'college_id');
    }

    public function moas()
    {
        return $this->hasMany(MOA::class);
    }
}
