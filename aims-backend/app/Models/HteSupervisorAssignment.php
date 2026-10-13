<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteSupervisorAssignment extends Model
{
    protected $fillable = ['supervisor_profile_id', 'hte_department_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function supervisorProfile()
    {
        return $this->belongsTo(SupervisorProfile::class);
    }

    public function department()
    {
        return $this->belongsTo(HteDepartment::class, 'hte_department_id');
    }
}
