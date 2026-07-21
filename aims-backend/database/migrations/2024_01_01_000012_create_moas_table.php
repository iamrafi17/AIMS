<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hte_id')->constrained()->cascadeOnDelete();
            $table->foreignId('college_id')->constrained();
            $table->string('file_path', 500);
            $table->date('effective_date');
            $table->date('expiration_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moas');
    }
};
