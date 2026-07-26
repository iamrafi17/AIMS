<?php

namespace App\Support;

use App\Models\User;

final class StaffId
{
    private const ROLE_PREFIXES = [
        'coordinator' => 'CO',
        'program_head' => 'PH',
        'vpaa' => 'VP',
        'supervisor' => 'SV',
    ];

    public static function isStaffRole(?string $role): bool
    {
        return isset(self::ROLE_PREFIXES[$role]);
    }

    public static function belongsToRole(?string $staffId, ?string $role): bool
    {
        if (! $staffId || ! self::isStaffRole($role)) {
            return false;
        }

        return str_starts_with($staffId, self::ROLE_PREFIXES[$role].'-');
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
            ->where('staff_id', 'like', $pattern.'%')
            ->pluck('staff_id')
            ->map(function (string $staffId) use ($pattern): int {
                return (int) substr($staffId, strlen($pattern));
            })
            ->max() ?? 0;

        do {
            $lastSequence++;
            $staffId = sprintf('%s%03d', $pattern, $lastSequence);
        } while (User::query()->where('staff_id', $staffId)->exists());

        return $staffId;
    }
}
