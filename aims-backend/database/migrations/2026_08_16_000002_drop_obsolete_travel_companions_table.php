<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('travel_companions');
    }

    public function down(): void
    {
        Schema::create('travel_companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_log_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40)->default('student');
            $table->string('contact', 40)->nullable();
            $table->string('relationship', 100)->nullable();
            $table->timestamps();
        });
    }
};
