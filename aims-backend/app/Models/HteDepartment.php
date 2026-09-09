<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteDepartment extends Model
{
    protected $fillable = [
        'hte_id',
        'department_name',
        'normalized_name',
    ];

    public function hte()
    {
        return $this->belongsTo(HTE::class, 'hte_id');
    }

    public function supervisors()
    {
        return $this->hasMany(Supervisor::class, 'hte_department_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'hte_department_id');
    }
}
