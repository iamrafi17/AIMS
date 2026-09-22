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
        'answer_case_sensitive',
    ];

    protected $hidden = [
        'answer_hash',
    ];

    protected function casts(): array
    {
        return ['answer_case_sensitive' => 'boolean'];
    }

    public static function normalizeAnswer(string $answer, bool $caseSensitive = false): string
    {
        $normalized = Str::of($answer)->squish();

        return ($caseSensitive ? $normalized : $normalized->lower())->toString();
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
