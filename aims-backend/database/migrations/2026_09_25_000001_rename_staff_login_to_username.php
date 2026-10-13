<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            return;
        }

        foreach (['user_name', 'user_id', 'staff_id'] as $oldName) {
            if (Schema::hasColumn('users', $oldName)) {
                Schema::table('users', function (Blueprint $table) use ($oldName): void {
                    $table->renameColumn($oldName, 'username');
                });

                return;
            }
        }

        throw new RuntimeException('No staff login column was found in users.');
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->renameColumn('username', 'user_id');
            });
        }
    }
};
