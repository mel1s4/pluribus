<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceOffersApiTest extends TestCase
{
    use RefreshDatabase;

    private function makePlaceForUser(User $user, string $name): Place
    {
        return Place::query()->create([
            'user_id' => $user->id,
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

    public function test_member_can_create_place_then_add_offer_via_json(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);

        $placeRes = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places', [
                'name' => 'Tagged spot',
                'slug' => 'tagged-spot',
                'description' => 'Hello',
                'tags' => ['foo', 'bar'],
            ])
            ->assertCreated();

        $placeId = (int) $placeRes->json('place.id');
        $this->assertGreaterThan(0, $placeId);

        $offerRes = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$placeId.'/offers', [
                'title' => 'Winter deal',
                'description' => 'Limited',
                'price' => '19.99',
                'tags' => ['deal', 'winter'],
            ])
            ->assertCreated();

        $this->assertSame('Winter deal', $offerRes->json('offer.title'));
        $this->assertSame('19.99', $offerRes->json('offer.price'));
        $this->assertSame(['deal', 'winter'], $offerRes->json('offer.tags'));
    }

    public function test_offer_must_belong_to_place_when_scoped(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $placeA = $this->makePlaceForUser($user, 'Place A');
        $placeB = $this->makePlaceForUser($user, 'Place B');
        $offerOnB = $placeB->offers()->create([
            'title' => 'B only',
            'description' => null,
            'price' => 5,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => [],
        ]);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson('/api/places/'.$placeA->id.'/offers/'.$offerOnB->id, [
                'title' => 'Hijack',
            ])
            ->assertNotFound();
    }
}
