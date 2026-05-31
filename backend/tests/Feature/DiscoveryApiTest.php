<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Community;
use App\Models\Place;
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

    public function test_map_discovery_guest_sees_only_public_places_and_community_posts(): void
    {
        $community = Community::current();
        $owner = User::factory()->create(['user_type' => 'member']);
        Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Public pin',
            'slug' => 'public-pin',
            'is_public' => true,
            'description' => null,
            'tags' => null,
            'latitude' => 40.0,
            'longitude' => -3.0,
            'location_type' => Place::LOCATION_POINT,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
            'service_schedule' => null,
        ]);
        Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private pin',
            'slug' => 'private-pin',
            'is_public' => false,
            'description' => null,
            'tags' => null,
            'latitude' => 41.0,
            'longitude' => -4.0,
            'location_type' => Place::LOCATION_POINT,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
            'service_schedule' => null,
        ]);
        Post::query()->create([
            'community_id' => $community->id,
            'author_id' => $owner->id,
            'type' => Post::TYPE_INFO,
            'title' => 'Community notice',
            'visibility_scope' => Post::VISIBILITY_COMMUNITY,
            'latitude' => 40.1,
            'longitude' => -3.1,
        ]);
        Post::query()->create([
            'community_id' => $community->id,
            'author_id' => $owner->id,
            'type' => Post::TYPE_INFO,
            'title' => 'Secret post',
            'visibility_scope' => Post::VISIBILITY_PRIVATE,
            'latitude' => 40.2,
            'longitude' => -3.2,
        ]);

        $res = $this->getJson('/api/discovery/map?entity=both');

        $res->assertOk();
        $placeNames = collect(data_get($res->json(), 'places.data', $res->json('places', [])))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
        $this->assertSame(['Public pin'], $placeNames);
        $postTitles = collect(data_get($res->json(), 'posts.data', $res->json('posts', [])))
            ->pluck('title')
            ->filter()
            ->values()
            ->all();
        $this->assertContains('Community notice', $postTitles);
        $this->assertNotContains('Secret post', $postTitles);
    }

    public function test_map_discovery_member_places_scope_public_limits_places(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);
        $owner = User::factory()->create(['user_type' => 'member']);
        Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Scoped public',
            'slug' => 'scoped-public',
            'is_public' => true,
            'description' => null,
            'tags' => null,
            'latitude' => 42.0,
            'longitude' => -5.0,
            'location_type' => Place::LOCATION_POINT,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
            'service_schedule' => null,
        ]);
        Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Scoped private',
            'slug' => 'scoped-private',
            'is_public' => false,
            'description' => null,
            'tags' => null,
            'latitude' => 42.1,
            'longitude' => -5.1,
            'location_type' => Place::LOCATION_POINT,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
            'service_schedule' => null,
        ]);

        $publicOnly = $this->actingAs($member)
            ->getJson('/api/discovery/map?entity=places&places_scope=public');
        $publicOnly->assertOk();
        $publicNames = collect(data_get($publicOnly->json(), 'places.data', $publicOnly->json('places', [])))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
        $this->assertSame(['Scoped public'], $publicNames);

        $allPlaces = $this->actingAs($member)
            ->getJson('/api/discovery/map?entity=places&places_scope=all');
        $allPlaces->assertOk();
        $allNames = collect(data_get($allPlaces->json(), 'places.data', $allPlaces->json('places', [])))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['Scoped private', 'Scoped public'], $allNames);
    }
}

