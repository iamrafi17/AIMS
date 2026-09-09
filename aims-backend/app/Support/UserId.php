<?php

namespace App\Support;

use App\Models\User;

final class UserId
{
    private const ROLE_PREFIXES = [
        'admin' => 'AD',
        'coordinator' => 'CO',
        'program_head' => 'PH',
        'vpaa' => 'VP',
        'supervisor' => 'SV',
    ];

    public static function isStaffRole(?string $role): bool
    {
        return isset(self::ROLE_PREFIXES[$role]);
    }

    public static function belongsToRole(?string $userId, ?string $role): bool
    {
        if (! $userId || ! self::isStaffRole($role)) {
            return false;
        }

        return str_starts_with($userId, self::ROLE_PREFIXES[$role].'-');
    }

    public static function generate(string $role): ?string
    {
        if (! self::isStaffRole($role)) {
            return null;
        }

        $prefix = self::ROLE_PREFIXES[$role];
        $year = now()->format('Y');
        $pattern = $prefix.'-'.$year.'-';

        $lastSequence = User::query()
            ->where('user_id', 'like', $pattern.'%')
            ->pluck('user_id')
            ->map(function (string $userId) use ($pattern): int {
                return (int) substr($userId, strlen($pattern));
            })
            ->max() ?? 0;

        do {
            $lastSequence++;
            $userId = sprintf('%s%03d', $pattern, $lastSequence);
        } while (User::query()->where('user_id', $userId)->exists());

        return $userId;
    }
}
