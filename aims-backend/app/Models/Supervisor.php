<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supervisor extends Model
{
    protected $fillable = [
        'hte_department_id',
        'full_name',
        'normalized_name',
        'user_id',
    ];

    public function department()
    {
        return $this->belongsTo(HteDepartment::class, 'hte_department_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function placements()
    {
        return $this->hasMany(StudentInternshipPlacement::class, 'supervisor_record_id');
    }
}
