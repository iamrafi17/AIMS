<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDocumentApproval extends Model
{
    public const FOR_DEAN_REVIEW = 'for_dean_review';
    public const FOR_REVIEW = 'for_review';
    public const REVIEWED = 'reviewed';
    public const FINAL_APPROVED = 'final_approved';
    public const RETURNED = 'returned';

    protected $fillable = [
        'student_internship_id', 'endorsed_by', 'endorsed_at',
        'status', 'decided_by', 'decided_at',
        'dean_decided_by', 'dean_decided_at', 'dean_remarks',
        'vpaa_reviewed_by', 'vpaa_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'endorsed_at' => 'datetime', 'decided_at' => 'datetime',
            'dean_decided_at' => 'datetime', 'vpaa_reviewed_at' => 'datetime',
        ];
    }

    public function internship()
    {
        return $this->belongsTo(StudentInternship::class, 'student_internship_id');
    }

    public function endorser()
    {
        return $this->belongsTo(User::class, 'endorsed_by');
    }

    public function decisionMaker()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function deanDecisionMaker()
    {
        return $this->belongsTo(User::class, 'dean_decided_by');
    }

    public function vpaaReviewer()
    {
        return $this->belongsTo(User::class, 'vpaa_reviewed_by');
    }
}
