<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('colleges', 'description')) {
            Schema::table('colleges', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }

        if (Schema::hasColumn('programs', 'description')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('colleges', 'description')) {
            Schema::table('colleges', function (Blueprint $table) {
                $table->text('description')->nullable()->after('code');
            });
        }

        if (! Schema::hasColumn('programs', 'description')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->text('description')->nullable()->after('code');
            });
        }
    }
};
