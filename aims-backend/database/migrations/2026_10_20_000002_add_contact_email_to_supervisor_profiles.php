<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_profiles', function (Blueprint $table): void {
            $table->string('contact_email')->nullable()->after('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_profiles', function (Blueprint $table): void {
            $table->dropColumn('contact_email');
        });
    }
};
