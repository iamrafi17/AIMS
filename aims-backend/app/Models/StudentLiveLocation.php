<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentLiveLocation extends Model
{
    protected $fillable = [
        'student_id',
        'student_internship_id',
        'attendance_id',
        'latitude',
        'longitude',
        'accuracy',
        'captured_at',
        'recorded_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'captured_at' => 'datetime',
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
