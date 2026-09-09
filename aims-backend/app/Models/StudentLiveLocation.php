<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentLiveLocation extends Model
{
    protected $fillable = [
        'student_id',
        'latitude',
        'longitude',
        'accuracy',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'captured_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
