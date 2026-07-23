<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('phone', 20)->nullable()->change();
            $table->foreignId('college_id')->nullable()->change();
            $table->foreignId('program_id')->nullable()->change();
            $table->integer('year_level')->nullable()->change();
            $table->string('parent_name', 255)->nullable()->change();
            $table->text('parent_address')->nullable()->change();
            $table->string('parent_phone', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Quick-created records can contain null profile fields, so reverting
        // these columns to required would risk destroying valid records.
    }
};
