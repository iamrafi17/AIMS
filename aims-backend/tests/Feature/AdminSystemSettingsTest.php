<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function test_empty_table_displays_defaults_and_only_saves_them_on_request(): void
    {
        DB::table('system_settings')->delete();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('has_missing_settings', true)
            ->assertJsonCount(2, 'settings.general')
            ->assertJsonCount(2, 'settings.internship')
            ->assertJsonCount(2, 'settings.security')
            ->assertJsonCount(2, 'settings.notifications');
        $this->assertDatabaseCount('system_settings', 0);

        $values = collect($response->json('settings'))->flatten(1)->pluck('value', 'key')->all();
        $this->putJson('/api/admin/settings', [
            'settings' => [...$values, 'campus_name' => 'Updated Campus', 'default_ojt_hours' => '600', 'email_notifications' => '0'],
        ])->assertOk();

        $this->assertDatabaseCount('system_settings', 8);
        $this->assertDatabaseHas('system_settings', ['key' => 'campus_name', 'value' => 'Updated Campus']);
        $this->assertDatabaseHas('system_settings', ['key' => 'default_ojt_hours', 'value' => '600']);
        $this->assertDatabaseHas('system_settings', ['key' => 'email_notifications', 'value' => '0']);
        $this->getJson('/api/admin/settings')->assertOk()->assertJsonCount(4, 'settings')
            ->assertJsonPath('has_missing_settings', false)
            ->assertJsonPath('settings.general.1.value', 'Updated Campus')
            ->assertJsonPath('settings.internship.0.value', '600')
            ->assertJsonPath('settings.notifications.0.value', '0');
    }

    public function test_missing_settings_use_defaults_without_overwriting_saved_values(): void
    {
        SystemSetting::where('key', 'default_ojt_hours')->update(['value' => '600']);
        SystemSetting::where('key', '!=', 'default_ojt_hours')->delete();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/settings')->assertOk()
            ->assertJsonPath('has_missing_settings', true)
            ->assertJsonPath('settings.internship.0.value', '600')
            ->assertJsonPath('settings.security.0.value', '120');
        $this->assertDatabaseCount('system_settings', 1);

        $this->putJson('/api/admin/settings', ['settings' => ['system_name' => 'AIMS']])->assertOk();
        $this->assertDatabaseCount('system_settings', 2);
        $this->assertDatabaseHas('system_settings', ['key' => 'default_ojt_hours', 'value' => '600']);
        $this->assertDatabaseHas('system_settings', ['key' => 'system_name', 'group' => 'general', 'value' => 'AIMS']);
    }

    public function test_invalid_settings_are_rejected_without_saving_partial_changes(): void
    {
        DB::table('system_settings')->delete();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->putJson('/api/admin/settings', [
            'settings' => ['system_name' => 'AIMS', 'default_ojt_hours' => 'invalid'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.default_ojt_hours');
        $this->putJson('/api/admin/settings', [
            'settings' => ['system_name' => 'AIMS', 'unknown_setting' => 'value'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings');
        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_saved_password_minimum_controls_generation_and_admin_resets(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([8, 10, 16] as $length) {
            $this->putJson('/api/admin/settings', [
                'settings' => ['minimum_password_length' => (string) $length],
            ])->assertOk();
            $this->getJson('/api/password-policy')->assertOk()->assertJsonPath('minimum_length', $length);

            $created = $this->postJson('/api/admin/users', [
                'name' => 'Password Test '.$length,
                'email' => 'password'.$length.'@example.com',
                'role' => 'admin',
            ])->assertCreated();
            $password = $created->json('temporary_password');
            $this->assertSame($length, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/\d/', $password);
            $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $password);
            $user = User::findOrFail($created->json('user.id'));

            $this->postJson('/api/admin/users/'.$user->id.'/reset-password', [
                'password' => str_repeat('x', $length - 1),
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->assertTrue(Hash::check($password, $user->fresh()->password));
            $this->postJson('/api/admin/users/'.$user->id.'/reset-password', [
                'password' => str_repeat('x', $length),
            ])->assertOk();
            $this->assertTrue(Hash::check(str_repeat('x', $length), $user->fresh()->password));
        }
    }

    public function test_password_policy_defaults_to_eight_without_writing_settings_and_rejects_invalid_limits(): void
    {
        SystemSetting::where('key', 'minimum_password_length')->delete();
        $this->getJson('/api/password-policy')->assertOk()->assertJsonPath('minimum_length', 8);
        $this->assertDatabaseMissing('system_settings', ['key' => 'minimum_password_length']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([0, 7, 73, 'invalid'] as $value) {
            $this->putJson('/api/admin/settings', [
                'settings' => ['minimum_password_length' => $value],
            ])->assertUnprocessable()->assertJsonValidationErrors('settings.minimum_password_length');
        }
    }
}
