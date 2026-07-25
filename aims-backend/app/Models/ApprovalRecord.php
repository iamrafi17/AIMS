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
        'decided_by',
        'remarks',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
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
                'decided_by' => null,
                'remarks' => null,
                'decided_at' => null,
            ],
        );
    }
}
