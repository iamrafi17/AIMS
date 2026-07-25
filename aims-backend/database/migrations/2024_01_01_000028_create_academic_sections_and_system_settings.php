<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedTinyInteger('year_level')->default(4);
            $table->string('academic_year', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['program_id', 'name', 'academic_year']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('label', 160);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('system_settings')->insert([
            ['group' => 'general', 'key' => 'system_name', 'value' => 'Academic Internship Monitoring System', 'type' => 'string', 'label' => 'System name', 'description' => 'Name displayed throughout the portal.', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'general', 'key' => 'campus_name', 'value' => 'Marinduque State University – Santa Cruz Campus', 'type' => 'string', 'label' => 'Campus name', 'description' => 'Official campus identity.', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'internship', 'key' => 'default_ojt_hours', 'value' => '486', 'type' => 'integer', 'label' => 'Default required OJT hours', 'description' => 'Used when an enrollment has no program-specific requirement.', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'internship', 'key' => 'allow_past_attendance', 'value' => '0', 'type' => 'boolean', 'label' => 'Allow past attendance by default', 'description' => 'Coordinators can still override this for individual students.', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'security', 'key' => 'session_timeout_minutes', 'value' => '120', 'type' => 'integer', 'label' => 'Session timeout (minutes)', 'description' => 'Recommended inactivity limit for authenticated sessions.', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'security', 'key' => 'minimum_password_length', 'value' => '8', 'type' => 'integer', 'label' => 'Minimum password length', 'description' => 'Minimum accepted account password length.', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'notifications', 'key' => 'email_notifications', 'value' => '1', 'type' => 'boolean', 'label' => 'Email notifications', 'description' => 'Send important approval and deadline notices by email.', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'notifications', 'key' => 'moa_expiration_days', 'value' => '60', 'type' => 'integer', 'label' => 'MOA expiration warning (days)', 'description' => 'Number of days before expiration alerts begin.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('academic_sections');
    }
};
