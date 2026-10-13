<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vpaa_can_create_draft_and_published_announcements_with_author_name(): void
    {
        $author = User::factory()->create(['role' => 'vpaa', 'name' => 'VPAA Author']);
        User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($author);

        foreach ([false, true] as $published) {
            $this->postJson('/api/vpaa/announcements', [
                'title' => $published ? 'Published notice' : 'Draft notice',
                'content' => 'Announcement body',
                'category' => 'general',
                'target_audience' => 'all',
                'is_published' => $published,
            ])->assertCreated()
                ->assertJsonPath('announcement.author.name', 'VPAA Author')
                ->assertJsonPath('announcement.is_published', $published);
        }

        $this->assertDatabaseCount('announcements', 2);
    }
}
