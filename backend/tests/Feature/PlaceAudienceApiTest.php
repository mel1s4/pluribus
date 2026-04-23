<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceAudienceApiTest extends TestCase
{
    use RefreshDatabase;

    private function makePlace(User $owner, string $name = 'Venue'): Place
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

    public function test_owner_can_list_pickable_members_and_create_audience(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $other = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner);

        $pick = $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places/'.$place->id.'/audience-members')
            ->assertOk();

        $ids = collect($pick->json('data'))->pluck('id')->all();
        $this->assertContains($owner->id, $ids);
        $this->assertContains($other->id, $ids);

        $create = $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/audiences', [
                'name' => 'Inner circle',
                'user_ids' => [$other->id],
            ])
            ->assertCreated();

        $this->assertSame('Inner circle', $create->json('audience.name'));
        $memberIds = collect($create->json('audience.members'))->pluck('id')->all();
        $this->assertEquals([$other->id], $memberIds);
    }

    public function test_non_owner_cannot_list_audience_members(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $intruder = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner);

        $this->actingAs($intruder)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places/'.$place->id.'/audience-members')
            ->assertForbidden();
    }

    public function test_audience_update_scoped_to_place(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $placeA = $this->makePlace($owner, 'A');
        $placeB = $this->makePlace($owner, 'B');
        $audienceOnB = $placeB->audiences()->create(['name' => 'B group']);
        $audienceOnB->members()->attach($owner->id);

        $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson('/api/places/'.$placeA->id.'/audiences/'.$audienceOnB->id, [
                'name' => 'Hijack',
            ])
            ->assertNotFound();
    }
}
