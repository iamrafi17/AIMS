<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity_type',
        'description',
    ];

    public static function record(User|int $user, string $activityType, string $description): self
    {
        return self::create([
            'user_id' => $user instanceof User ? $user->getKey() : $user,
            'activity_type' => $activityType,
            'description' => $description,
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
