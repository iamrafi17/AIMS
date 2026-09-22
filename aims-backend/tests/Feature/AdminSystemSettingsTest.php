<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_restores_missing_settings_and_preserves_existing_values(): void
    {
        SystemSetting::where('key', 'default_ojt_hours')->update(['value' => '600']);
        SystemSetting::where('key', '!=', 'default_ojt_hours')->delete();
        $repair = require database_path('migrations/2026_09_18_000001_restore_missing_system_settings.php');
        $repair->up();
        $repair->up();

        $this->assertDatabaseCount('system_settings', 8);
        $this->assertDatabaseHas('system_settings', ['key' => 'default_ojt_hours', 'value' => '600']);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonCount(2, 'settings.general')
            ->assertJsonCount(2, 'settings.internship')
            ->assertJsonCount(2, 'settings.security')
            ->assertJsonCount(2, 'settings.notifications');
    }

    public function test_repair_restores_empty_table_and_settings_can_be_saved(): void
    {
        DB::table('system_settings')->delete();
        $repair = require database_path('migrations/2026_09_18_000001_restore_missing_system_settings.php');
        $repair->up();

        $this->assertDatabaseCount('system_settings', 8);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/admin/settings', [
            'settings' => ['campus_name' => 'Updated Campus', 'default_ojt_hours' => '600', 'email_notifications' => '0'],
        ])->assertOk();

        $this->assertDatabaseHas('system_settings', ['key' => 'campus_name', 'value' => 'Updated Campus']);
        $this->assertDatabaseHas('system_settings', ['key' => 'default_ojt_hours', 'value' => '600']);
        $this->assertDatabaseHas('system_settings', ['key' => 'email_notifications', 'value' => '0']);
        $this->getJson('/api/admin/settings')->assertOk()->assertJsonCount(4, 'settings');
    }
}
