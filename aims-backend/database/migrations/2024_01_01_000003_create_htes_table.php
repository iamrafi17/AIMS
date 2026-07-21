<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('htes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('address');
            $table->string('contact_person', 255);
            $table->string('contact_email', 255);
            $table->string('contact_phone', 20);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('geofence_radius')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('htes');
    }
};
