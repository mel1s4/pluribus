<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceRequirement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceRequirementsApiTest extends TestCase
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

    public function test_member_can_add_one_time_requirement_via_json(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'Shop');

        $res = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/requirements', [
                'title' => 'Flour',
                'description' => 'For baking',
                'quantity' => '12.5',
                'unit' => 'kg',
                'recurrence_mode' => PlaceRequirement::RECURRENCE_ONCE,
                'tags' => ['bakery'],
            ])
            ->assertCreated();

        $this->assertSame('Flour', $res->json('requirement.title'));
        $this->assertSame('12.5000', $res->json('requirement.quantity'));
        $this->assertSame('kg', $res->json('requirement.unit'));
        $this->assertSame('once', $res->json('requirement.recurrence_mode'));
        $this->assertSame([], $res->json('requirement.recurrence_weekdays'));
        $this->assertSame(['bakery'], $res->json('requirement.tags'));
    }

    public function test_weekly_requirement_requires_weekdays(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'Shop');

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/requirements', [
                'title' => 'Milk',
                'quantity' => '2',
                'unit' => 'L',
                'recurrence_mode' => PlaceRequirement::RECURRENCE_WEEKLY,
                'recurrence_weekdays' => [],
            ])
            ->assertUnprocessable();
    }

    public function test_weekly_requirement_persists_weekdays(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'Shop');

        $res = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/requirements', [
                'title' => 'Milk',
                'quantity' => '2',
                'unit' => 'L',
                'recurrence_mode' => PlaceRequirement::RECURRENCE_WEEKLY,
                'recurrence_weekdays' => ['mon', 'fri'],
            ])
            ->assertCreated();

        $this->assertSame(['mon', 'fri'], $res->json('requirement.recurrence_weekdays'));
    }

    public function test_requirement_must_belong_to_place_when_scoped(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $placeA = $this->makePlaceForUser($user, 'Place A');
        $placeB = $this->makePlaceForUser($user, 'Place B');
        $reqOnB = $placeB->requirements()->create([
            'title' => 'B only',
            'description' => null,
            'quantity' => 1,
            'unit' => 'box',
            'recurrence_mode' => PlaceRequirement::RECURRENCE_ONCE,
            'recurrence_weekdays' => null,
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson('/api/places/'.$placeA->id.'/requirements/'.$reqOnB->id, [
                'title' => 'Hijack',
            ])
            ->assertNotFound();
    }
}
