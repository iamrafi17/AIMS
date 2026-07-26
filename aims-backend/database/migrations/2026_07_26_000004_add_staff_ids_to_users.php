<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_id', 30)->nullable()->unique()->after('email');
        });

        $prefixes = [
            'coordinator' => 'CO',
            'program_head' => 'PH',
            'vpaa' => 'VP',
            'supervisor' => 'SV',
        ];
        $year = now()->format('Y');

        foreach ($prefixes as $role => $prefix) {
            $sequence = 0;

            DB::table('users')
                ->where('role', $role)
                ->whereNull('staff_id')
                ->orderBy('id')
                ->each(function (object $user) use (&$sequence, $prefix, $year): void {
                    $sequence++;
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['staff_id' => sprintf('%s-%s-%03d', $prefix, $year, $sequence)]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['staff_id']);
            $table->dropColumn('staff_id');
        });
    }
};
