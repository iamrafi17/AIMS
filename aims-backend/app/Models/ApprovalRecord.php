<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRecord extends Model
{
    use HasFactory;

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_DEPLOYMENT = 'deployment';

    public const TYPE_MOA = 'moa';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'status',
        'submitted_by',
        'submitted_at',
        'decided_by',
        'remarks',
        'decided_at',
        'deployment_internship_id',
        'deployment_stage',
        'deployment_snapshot',
        'dean_forwarded_by',
        'dean_forwarded_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'deployment_snapshot' => 'array',
            'dean_forwarded_at' => 'datetime',
        ];
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decisionMaker()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function deploymentInternship()
    {
        return $this->belongsTo(StudentInternship::class, 'deployment_internship_id');
    }

    public function deanForwarder()
    {
        return $this->belongsTo(User::class, 'dean_forwarded_by');
    }

    public static function submit(string $subjectType, int $subjectId, ?int $submittedBy): self
    {
        return static::query()->updateOrCreate(
            [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'status' => 'pending',
            ],
            [
                'submitted_by' => $submittedBy,
                'submitted_at' => now(),
                'decided_by' => null,
                'remarks' => null,
                'decided_at' => null,
            ],
        );
    }
}
