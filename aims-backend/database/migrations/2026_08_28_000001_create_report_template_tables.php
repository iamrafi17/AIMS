<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type', 80);
            $table->string('name');
            $table->string('original_filename');
            $table->string('file_path')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('checksum', 64);
            $table->string('version', 40)->nullable();
            $table->string('academic_year', 20);
            $table->string('semester', 40);
            $table->text('description')->nullable();
            $table->string('validation_status', 30)->default('pending');
            $table->json('validation_result')->nullable();
            $table->json('detected_placeholders')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('active_key')->nullable()->unique();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['program_id', 'report_type', 'academic_year', 'semester'], 'report_templates_scope_index');
            $table->index(['program_id', 'is_active'], 'report_templates_program_active_index');
            $table->index('checksum');
        });

        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_template_id')->nullable()->constrained('report_templates')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type', 80);
            $table->string('file_path');
            $table->string('filename');
            $table->string('format', 10)->default('pdf');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->json('data_snapshot')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'report_type', 'created_at'], 'generated_reports_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('report_templates');
    }
};
