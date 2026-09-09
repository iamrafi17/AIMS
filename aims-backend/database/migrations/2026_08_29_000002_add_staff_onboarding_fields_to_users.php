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
            $table->string('email')->nullable()->change();
            $table->boolean('requires_password_change')->default(false)->after('is_active');
            $table->boolean('requires_profile_setup')->default(false)->after('requires_security_setup');
            $table->timestamp('profile_completed_at')->nullable()->after('requires_profile_setup');
        });

        DB::table('users')
            ->where('requires_security_setup', true)
            ->update([
                'requires_password_change' => true,
                'requires_profile_setup' => true,
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNull('email')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                DB::table('users')->where('id', $user->id)->update([
                    'email' => 'archived-user-'.$user->id.'@aims.invalid',
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'requires_password_change',
                'requires_profile_setup',
                'profile_completed_at',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
