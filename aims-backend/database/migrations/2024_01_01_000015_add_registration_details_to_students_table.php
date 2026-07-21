<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('parent_relationship', 50)->nullable()->after('parent_name');
            $table->string('internship_semester', 30)->nullable()->after('hte_id');
            $table->string('internship_year', 20)->nullable()->after('internship_semester');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['parent_relationship', 'internship_semester', 'internship_year']);
        });
    }
};
