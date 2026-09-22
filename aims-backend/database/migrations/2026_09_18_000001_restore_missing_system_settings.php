<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['general', 'system_name', 'Academic Internship Monitoring System', 'string', 'System name', 'Name displayed throughout the portal.'],
            ['general', 'campus_name', 'Marinduque State University – Santa Cruz Campus', 'string', 'Campus name', 'Official campus identity.'],
            ['internship', 'default_ojt_hours', '486', 'integer', 'Default required OJT hours', 'Used when an enrollment has no program-specific requirement.'],
            ['internship', 'allow_past_attendance', '0', 'boolean', 'Allow past attendance by default', 'Coordinators can still override this for individual students.'],
            ['security', 'session_timeout_minutes', '120', 'integer', 'Session timeout (minutes)', 'Recommended inactivity limit for authenticated sessions.'],
            ['security', 'minimum_password_length', '8', 'integer', 'Minimum password length', 'Minimum accepted account password length.'],
            ['notifications', 'email_notifications', '1', 'boolean', 'Email notifications', 'Send important approval and deadline notices by email.'],
            ['notifications', 'moa_expiration_days', '60', 'integer', 'MOA expiration warning (days)', 'Number of days before expiration alerts begin.'],
        ];
        $now = now();

        DB::table('system_settings')->insertOrIgnore(array_map(
            fn (array $setting) => [
                'group' => $setting[0],
                'key' => $setting[1],
                'value' => $setting[2],
                'type' => $setting[3],
                'label' => $setting[4],
                'description' => $setting[5],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $defaults,
        ));
    }

    public function down(): void
    {
        // Keep restored configuration values when rolling back this repair.
    }
};
