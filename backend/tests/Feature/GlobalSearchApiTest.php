<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceAudience;
use App\Models\PlaceOffer;
use App\Models\PlaceRequirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchApiTest extends TestCase
{
    use RefreshDatabase;

    private function makePlaceForUser(User $user, string $name, array $tags = []): Place
    {
        return Place::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'description' => null,
            'tags' => $tags,
            'latitude' => null,
            'longitude' => null,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
        ]);
    }

    public function test_it_returns_grouped_results_for_matching_query(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice Searchable',
            'username' => 'alice_searchable',
            'user_type' => 'member',
        ]);
        $owner = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($owner, 'Bakery Place', ['starter-kit']);
        PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'Starter Bread',
            'description' => null,
            'price' => 15,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => ['starter-kit'],
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);
        PlaceRequirement::query()->create([
            'place_id' => $place->id,
            'title' => 'Starter Flour',
            'description' => null,
            'quantity' => 1,
            'unit' => 'kg',
            'recurrence_mode' => PlaceRequirement::RECURRENCE_ONCE,
            'recurrence_weekdays' => null,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => ['starter-kit'],
            'visibility_scope' => PlaceRequirement::VISIBILITY_SCOPE_PUBLIC,
        ]);

        $res = $this->actingAs($user)->getJson('/api/global-search?q=starter-kit');
        $res->assertOk()
            ->assertJsonStructure(['members', 'offers', 'requirements', 'places']);
        $this->assertNotEmpty($res->json('offers'));
        $this->assertNotEmpty($res->json('requirements'));
        $this->assertNotEmpty($res->json('places'));
    }

    public function test_it_hides_audience_scoped_posts_from_non_members(): void
    {
        $viewer = User::factory()->create(['user_type' => 'member']);
        $owner = User::factory()->create(['user_type' => 'member']);
        $member = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($owner, 'Audience Place', ['gated-tag']);
        $audience = PlaceAudience::query()->create([
            'place_id' => $place->id,
            'name' => 'VIP',
        ]);
        $audience->members()->attach($member->id);

        $offer = PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'Gated Offer',
            'description' => null,
            'price' => 30,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => ['gated-tag'],
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_AUDIENCE,
        ]);
        $offer->audiences()->attach($audience->id);

        $requirement = PlaceRequirement::query()->create([
            'place_id' => $place->id,
            'title' => 'Gated Requirement',
            'description' => null,
            'quantity' => 2,
            'unit' => 'box',
            'recurrence_mode' => PlaceRequirement::RECURRENCE_ONCE,
            'recurrence_weekdays' => null,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => ['gated-tag'],
            'visibility_scope' => PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE,
        ]);
        $requirement->audiences()->attach($audience->id);

        $this->actingAs($viewer)
            ->getJson('/api/global-search?q=gated-tag')
            ->assertOk()
            ->assertJsonCount(0, 'offers')
            ->assertJsonCount(0, 'requirements');
    }
}
