<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPortalVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_portal_shows_only_current_published_public_audience_announcements(): void
    {
        $author = User::factory()->create(['role' => 'vpaa']);
        foreach ([
            'archived' => ['archived_at' => now()],
            'future' => ['scheduled_at' => now()->addDay()],
            'draft' => ['is_published' => false],
            'staff' => ['target_audience' => 'coordinators'],
            'visible' => [],
            'scheduled-visible' => ['scheduled_at' => now()->subMinute(), 'target_audience' => 'students'],
        ] as $title => $overrides) {
            Announcement::create(array_merge([
                'author_id' => $author->id, 'title' => $title, 'content' => 'Announcement body',
                'target_audience' => 'all', 'is_published' => true, 'published_at' => now(),
            ], $overrides));
        }

        $response = $this->getJson('/api/portal')->assertOk()->assertJsonCount(2, 'announcements');
        $this->assertEqualsCanonicalizing(['visible', 'scheduled-visible'], array_column($response->json('announcements'), 'title'));
    }
}
