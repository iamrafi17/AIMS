<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGuardian extends Model
{
    protected $fillable = [
        'student_id',
        'full_name',
        'relationship',
        'address',
        'contact_number',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
