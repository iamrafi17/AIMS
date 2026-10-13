<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->boolean('smart_clock_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->dropColumn('smart_clock_enabled');
        });
    }
};
