<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceRequirement;
use App\Models\PlaceRequirementResponse;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceRequirementResponsesApiTest extends TestCase
{
    use RefreshDatabase;

    private function makePlace(User $owner, string $name): Place
    {
        return Place::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'description' => null,
            'tags' => null,
            'latitude' => null,
            'longitude' => null,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
        ]);
    }

    public function test_authenticated_user_can_post_response_on_requirement(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $responder = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner, 'Shop');
        $req = $place->requirements()->create([
            'title' => 'Need boxes',
            'description' => null,
            'quantity' => 10,
            'unit' => 'pcs',
            'recurrence_mode' => PlaceRequirement::RECURRENCE_ONCE,
            'recurrence_weekdays' => null,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'example_place_offer_id' => null,
        ]);

        $res = $this->actingAs($responder)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/requirements/'.$req->id.'/responses', [
                'title' => 'I can supply',
                'description' => 'Cardboard',
                'price' => '15.00',
                'visibility' => PlaceRequirementResponse::VISIBILITY_COMMUNITY,
                'tags' => [],
            ])
            ->assertCreated();

        $this->assertSame('I can supply', $res->json('response.title'));
        $this->assertSame('community', $res->json('response.visibility'));
    }

    public function test_place_show_includes_community_response_for_stranger(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $responder = User::factory()->create(['user_type' => 'member']);
        $stranger = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner, 'Shop');
        $req = $place->requirements()->create([
            'title' => 'Need boxes',
            'description' => null,
            'quantity' => 10,
            'unit' => 'pcs',
            'recurrence_mode' => PlaceRequirement::RECURRENCE_ONCE,
            'recurrence_weekdays' => null,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'example_place_offer_id' => null,
        ]);
        $req->responses()->create([
            'user_id' => $responder->id,
            'title' => 'Public bid',
            'description' => null,
            'price' => 5,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'visibility' => PlaceRequirementResponse::VISIBILITY_COMMUNITY,
        ]);
        $req->responses()->create([
            'user_id' => $responder->id,
            'title' => 'Private bid',
            'description' => null,
            'price' => 3,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'visibility' => PlaceRequirementResponse::VISIBILITY_CREATOR_ONLY,
        ]);

        $json = $this->actingAs($stranger)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places/'.$place->id)
            ->assertOk()
            ->json('place.requirements');

        $this->assertIsArray($json);
        $this->assertCount(1, $json);
        $this->assertCount(1, $json[0]['community_responses']);
        $this->assertSame('Public bid', $json[0]['community_responses'][0]['title']);
        $this->assertArrayNotHasKey('offers_made', $json[0]);
    }

    public function test_owner_sees_offers_made_including_private(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $responder = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner, 'Shop');
        $req = $place->requirements()->create([
            'title' => 'Need boxes',
            'description' => null,
            'quantity' => 10,
            'unit' => 'pcs',
            'recurrence_mode' => PlaceRequirement::RECURRENCE_ONCE,
            'recurrence_weekdays' => null,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'example_place_offer_id' => null,
        ]);
        $req->responses()->create([
            'user_id' => $responder->id,
            'title' => 'Public bid',
            'description' => null,
            'price' => 5,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'visibility' => PlaceRequirementResponse::VISIBILITY_COMMUNITY,
        ]);
        $req->responses()->create([
            'user_id' => $responder->id,
            'title' => 'Private bid',
            'description' => null,
            'price' => 3,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'visibility' => PlaceRequirementResponse::VISIBILITY_CREATOR_ONLY,
        ]);

        $json = $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places/'.$place->id)
            ->assertOk()
            ->json('place.requirements.0');

        $this->assertArrayHasKey('offers_made', $json);
        $this->assertCount(2, $json['offers_made']);
    }

    public function test_requirement_can_reference_example_offer_from_another_place(): void
    {
        $ownerA = User::factory()->create(['user_type' => 'member']);
        $ownerB = User::factory()->create(['user_type' => 'member']);
        $placeA = $this->makePlace($ownerA, 'Shop A');
        $placeB = $this->makePlace($ownerB, 'Shop B');
        $offerB = $placeB->offers()->create([
            'title' => 'Sample widget',
            'description' => null,
            'price' => 9.99,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => [],
        ]);

        $this->actingAs($ownerA)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$placeA->id.'/requirements', [
                'title' => 'Want like this',
                'quantity' => 1,
                'unit' => 'lot',
                'recurrence_mode' => PlaceRequirement::RECURRENCE_ONCE,
                'example_place_offer_id' => $offerB->id,
            ])
            ->assertCreated();

        $row = PlaceRequirement::query()->where('place_id', $placeA->id)->first();
        $this->assertSame($offerB->id, (int) $row->example_place_offer_id);
    }

    public function test_non_manager_cannot_list_requirements_index(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $stranger = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner, 'Shop');
        $place->requirements()->create([
            'title' => 'Need',
            'description' => null,
            'quantity' => 1,
            'unit' => 'u',
            'recurrence_mode' => PlaceRequirement::RECURRENCE_ONCE,
            'recurrence_weekdays' => null,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'example_place_offer_id' => null,
        ]);

        $this->actingAs($stranger)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places/'.$place->id.'/requirements')
            ->assertForbidden();
    }
}
