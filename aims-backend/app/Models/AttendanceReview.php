<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceReview extends Model
{
    protected $fillable = [
        'attendance_id', 'review_type', 'decision', 'feedback', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
