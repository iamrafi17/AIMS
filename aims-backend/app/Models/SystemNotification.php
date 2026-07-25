<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'message', 'action_url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function sendToUser(?int $userId, string $title, string $message, string $type = 'information', ?string $actionUrl = null): ?self
    {
        if (! $userId) return null;

        return static::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'action_url' => $actionUrl,
        ]);
    }

    public static function sendToRole(string $role, string $title, string $message, string $type = 'information', ?string $actionUrl = null): void
    {
        User::where('role', $role)->where('is_active', true)->pluck('id')->each(
            fn (int $userId) => static::sendToUser($userId, $title, $message, $type, $actionUrl)
        );
    }
}
