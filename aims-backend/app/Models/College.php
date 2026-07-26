<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class College extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'required_ojt_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'required_ojt_hours' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function htes()
    {
        return $this->belongsToMany(HTE::class, 'moas', 'college_id', 'hte_id');
    }
}
