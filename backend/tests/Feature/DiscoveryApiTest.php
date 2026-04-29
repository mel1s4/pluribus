<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Community;
use App\Models\Post;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_discovery_returns_visible_posts(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        Post::query()->create([
            'community_id' => $community->id,
            'author_id' => $user->id,
            'type' => Post::TYPE_EVENT,
            'title' => 'Town Hall',
            'visibility_scope' => Post::VISIBILITY_PRIVATE,
            'start_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/discovery/calendar')
            ->assertOk()
            ->assertJsonPath('events.0.title', 'Town Hall')
            ->assertJsonPath('events.0.entity_type', 'post');
    }

    public function test_calendar_discovery_includes_tasks_with_calendar_id(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $calendar = Calendar::query()->create([
            'community_id' => $community->id,
            'owner_id' => $user->id,
            'name' => 'Work',
            'color' => '#22c55e',
            'visibility_scope' => Calendar::VISIBILITY_PRIVATE,
            'is_default' => false,
        ]);
        Task::query()->create([
            'community_id' => $community->id,
            'author_id' => $user->id,
            'calendar_id' => $calendar->id,
            'title' => 'Ship feature',
            'visibility_scope' => Task::VISIBILITY_PRIVATE,
            'start_at' => now()->addDays(2),
        ]);

        $this->actingAs($user)
            ->getJson('/api/discovery/calendar')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Ship feature', 'entity_type' => 'task']);
    }

    public function test_map_discovery_returns_posts_collection_key(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($user)
            ->getJson('/api/discovery/map?entity=posts')
            ->assertOk()
            ->assertJsonStructure(['posts', 'places']);
    }
}

