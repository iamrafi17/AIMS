<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->string('holiday_type', 40)->default('national_holiday')->after('date');
            $table->string('official_reference', 255)->nullable()->after('description');
            $table->string('source_url', 2048)->nullable()->after('official_reference');
            $table->string('affected_area', 255)->nullable()->after('source_url');
            $table->index(['program_id', 'holiday_type', 'date']);
        });

        Schema::create('holiday_hte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_id')->constrained('holidays')->cascadeOnDelete();
            $table->foreignId('hte_id')->constrained('htes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['holiday_id', 'hte_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_hte');

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropIndex(['program_id', 'holiday_type', 'date']);
            $table->dropColumn(['holiday_type', 'official_reference', 'source_url', 'affected_area']);
        });
    }
};
