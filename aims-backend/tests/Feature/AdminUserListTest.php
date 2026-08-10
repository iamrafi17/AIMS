<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_users_by_college_and_choose_a_page_size(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $college = College::create(['name' => 'College of Computing', 'code' => 'CCS']);
        $otherCollege = College::create(['name' => 'College of Education', 'code' => 'CED']);
        $matchingUser = User::factory()->create(['role' => 'coordinator', 'college_id' => $college->id]);
        $otherUser = User::factory()->create(['role' => 'coordinator', 'college_id' => $otherCollege->id]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/users?college_id={$college->id}&per_page=10")
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $matchingUser->id)
            ->assertJsonMissing(['id' => $otherUser->id]);
    }
}
