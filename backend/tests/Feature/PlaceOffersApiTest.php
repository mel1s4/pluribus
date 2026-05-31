<?php

namespace Tests\Feature;

use App\Models\Community;
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
                'category' => 'Seasonal specials',
            ])
            ->assertCreated();

        $this->assertSame('Winter deal', $offerRes->json('offer.title'));
        $this->assertSame('19.99', $offerRes->json('offer.price'));
        $this->assertNull($offerRes->json('offer.local_price'));
        $this->assertSame(['deal', 'winter'], $offerRes->json('offer.tags'));
        $this->assertSame('Seasonal specials', $offerRes->json('offer.category'));

        $offerId = (int) $offerRes->json('offer.id');
        $patchRes = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson('/api/places/'.$placeId.'/offers/'.$offerId, [
                'category' => 'Clearance',
            ])
            ->assertOk();
        $this->assertSame('Clearance', $patchRes->json('offer.category'));
    }

    public function test_can_create_offer_with_community_credits_only(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'Credits shop');

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/offers', [
                'title' => 'Credits item',
                'price' => '12.50',
            ])
            ->assertCreated()
            ->assertJsonPath('offer.price', '12.50')
            ->assertJsonPath('offer.local_price', null);
    }

    public function test_can_create_offer_with_local_price_when_community_configured(): void
    {
        Community::current()->update(['local_currency_code' => 'MXN']);
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'Local shop');

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/offers', [
                'title' => 'Local item',
                'local_price' => '89.00',
            ])
            ->assertCreated()
            ->assertJsonPath('offer.price', null)
            ->assertJsonPath('offer.local_price', '89.00')
            ->assertJsonPath('offer.local_currency_code', 'MXN');
    }

    public function test_can_create_offer_with_both_prices(): void
    {
        Community::current()->update(['local_currency_code' => 'USD']);
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'Dual shop');

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/offers', [
                'title' => 'Dual item',
                'price' => '150.00',
                'local_price' => '25.00',
            ])
            ->assertCreated()
            ->assertJsonPath('offer.price', '150.00')
            ->assertJsonPath('offer.local_price', '25.00')
            ->assertJsonPath('offer.local_currency_code', 'USD');
    }

    public function test_can_create_offer_without_prices(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'Free shop');

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/offers', [
                'title' => 'Unpriced item',
            ])
            ->assertCreated()
            ->assertJsonPath('offer.price', null)
            ->assertJsonPath('offer.local_price', null);
    }

    public function test_local_price_rejected_when_community_has_no_local_currency(): void
    {
        Community::current()->update(['local_currency_code' => null]);
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'No local currency');

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/offers', [
                'title' => 'Bad local price',
                'local_price' => '10.00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['local_price']);
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
