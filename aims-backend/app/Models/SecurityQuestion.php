<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function userAnswers()
    {
        return $this->hasMany(UserSecurityQuestion::class);
    }
}
