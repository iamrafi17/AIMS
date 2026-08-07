<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserSecurityQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'security_question_id',
        'position',
        'answer_hash',
    ];

    protected $hidden = [
        'answer_hash',
    ];

    public static function normalizeAnswer(string $answer): string
    {
        return Str::of($answer)->squish()->lower()->toString();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function question()
    {
        return $this->belongsTo(SecurityQuestion::class, 'security_question_id');
    }
}
