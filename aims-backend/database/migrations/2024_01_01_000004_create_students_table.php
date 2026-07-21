<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('student_id', 20)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('birth_date');
            $table->text('address');
            $table->string('phone', 20);
            $table->foreignId('college_id')->constrained();
            $table->foreignId('program_id')->constrained();
            $table->integer('year_level');
            $table->string('section', 50);
            $table->string('parent_name', 255);
            $table->text('parent_address');
            $table->string('parent_phone', 20);
            $table->foreignId('hte_id')->nullable()->constrained();
            $table->enum('internship_status', ['pending', 'active', 'completed', 'dropped'])->default('pending');
            $table->enum('registration_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
