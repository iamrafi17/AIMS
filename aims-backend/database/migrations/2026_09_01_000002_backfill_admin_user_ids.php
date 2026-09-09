<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $year = now()->format('Y');
        $pattern = 'AD-'.$year.'-';
        $sequence = DB::table('users')
            ->where('user_id', 'like', $pattern.'%')
            ->pluck('user_id')
            ->map(fn (string $userId): int => (int) substr($userId, strlen($pattern)))
            ->max() ?? 0;

        DB::table('users')
            ->where('role', 'admin')
            ->whereNull('user_id')
            ->orderBy('id')
            ->each(function (object $user) use (&$sequence, $pattern): void {
                $sequence++;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['user_id' => sprintf('%s%03d', $pattern, $sequence)]);
            });
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->where('user_id', 'like', 'AD-%')
            ->update(['user_id' => null]);
    }
};
