<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MOA extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'moas';

    protected $appends = ['college'];

    protected $fillable = [
        'hte_id',
        'program_id',
        'file_path',
        'effective_date',
        'expiration_date',
        'status',
        'program_status',
        'program_feedback',
        'program_reviewed_by',
        'program_reviewed_at',
        'dean_endorsed_by',
        'dean_endorsed_at',
        'dean_remarks',
        'approved_by',
        'approved_at',
        'vpaa_reviewed_by',
        'vpaa_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'approved_at' => 'datetime',
            'vpaa_reviewed_at' => 'datetime',
            'program_reviewed_at' => 'datetime',
            'dean_endorsed_at' => 'datetime',
        ];
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class, 'hte_id');
    }

    public function getCollegeAttribute(): ?College
    {
        return $this->program?->college;
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deanEndorser()
    {
        return $this->belongsTo(User::class, 'dean_endorsed_by');
    }

    public function vpaaReviewer()
    {
        return $this->belongsTo(User::class, 'vpaa_reviewed_by');
    }

    public function approvalRecord()
    {
        return $this->hasOne(ApprovalRecord::class, 'subject_id')->where('subject_type', ApprovalRecord::TYPE_MOA)->latestOfMany();
    }

    public function programReviewer()
    {
        return $this->belongsTo(User::class, 'program_reviewed_by');
    }
}
