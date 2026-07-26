<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MOA extends Model
{
    use HasFactory;

    protected $table = 'moas';

    protected $fillable = [
        'hte_id',
        'college_id',
        'program_id',
        'file_path',
        'effective_date',
        'expiration_date',
        'status',
        'program_status',
        'program_feedback',
        'program_reviewed_by',
        'program_reviewed_at',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'approved_at' => 'datetime',
            'program_reviewed_at' => 'datetime',
        ];
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class, 'hte_id');
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function programReviewer()
    {
        return $this->belongsTo(User::class, 'program_reviewed_by');
    }
}
