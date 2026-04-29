<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlacePublicStorefrontApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_fetch_public_place_by_slug(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $place = Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Open café',
            'slug' => 'open-cafe',
            'is_public' => true,
            'description' => 'Welcome',
            'tags' => null,
            'latitude' => null,
            'longitude' => null,
            'location_type' => Place::LOCATION_NONE,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
            'service_schedule' => null,
            'brand_links' => [
                ['title' => 'Website', 'url' => 'https://open-cafe.test', 'icon' => 'website'],
            ],
        ]);

        $res = $this->getJson('/api/places/open-cafe/public');

        $res->assertOk();
        $res->assertJsonPath('place.slug', 'open-cafe');
        $res->assertJsonPath('place.is_public', true);
        $res->assertJsonPath('place.name', 'Open café');
        $res->assertJsonPath('place.brand_links.0.title', 'Website');
        $res->assertJsonPath('place.brand_links.0.icon', 'website');
    }

    public function test_guest_cannot_fetch_private_place_by_slug(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Secret lab',
            'slug' => 'secret-lab',
            'is_public' => false,
            'description' => null,
            'tags' => null,
            'latitude' => null,
            'longitude' => null,
            'location_type' => Place::LOCATION_NONE,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
            'service_schedule' => null,
        ]);

        $this->getJson('/api/places/secret-lab/public')->assertForbidden();
    }

    public function test_authenticated_user_can_fetch_private_place_via_public_endpoint(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $viewer = User::factory()->create(['user_type' => 'member']);
        Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Members only',
            'slug' => 'members-only',
            'is_public' => false,
            'description' => null,
            'tags' => null,
            'latitude' => null,
            'longitude' => null,
            'location_type' => Place::LOCATION_NONE,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
            'service_schedule' => null,
        ]);

        $this->actingAs($viewer)
            ->getJson('/api/places/members-only/public')
            ->assertOk()
            ->assertJsonPath('place.slug', 'members-only');
    }
}
