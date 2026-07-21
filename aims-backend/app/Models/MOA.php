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
        'file_path',
        'effective_date',
        'expiration_date',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function hte()
    {
        return $this->belongsTo(HTE::class);
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
