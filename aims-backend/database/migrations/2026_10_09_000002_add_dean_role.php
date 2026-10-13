<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['code' => 'dean'],
            ['name' => 'Dean', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('code', 'dean')->whereNotIn('id', DB::table('users')->select('role_id'))->delete();
    }
};
