<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MemberProfileApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://localhost:9123'];
    }

    private function statefulJson(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withHeaders($this->statefulHeaders())
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    public function test_guest_cannot_fetch_member_profile(): void
    {
        $user = User::factory()->create();

        $this->statefulJson('GET', '/api/members/'.$user->id)
            ->assertUnauthorized();
    }

    public function test_authenticated_user_receives_member_profile_and_places(): void
    {
        $owner = User::factory()->create([
            'name' => 'Owner Person',
            'phone_numbers' => ['+1 555 0001'],
        ]);
        $editor = User::factory()->create(['name' => 'Editor Person']);
        $place = Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Café Test',
            'description' => 'Nice spot',
            'tags' => ['cafe'],
            'latitude' => null,
            'longitude' => null,
            'location_type' => Place::LOCATION_NONE,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
        ]);
        $place->administrators()->attach($editor->id, ['role' => Place::ADMIN_ROLE_EDITOR]);

        $viewer = User::factory()->create();

        $this->actingAs($viewer);

        $resOwner = $this->statefulJson('GET', '/api/members/'.$owner->id)
            ->assertOk()
            ->assertJsonPath('member.name', 'Owner Person')
            ->assertJsonPath('member.phone_numbers.0', '+1 555 0001');

        $places = $resOwner->json('places');
        $this->assertIsArray($places);
        $this->assertCount(1, $places);
        $this->assertSame('Café Test', $places[0]['name']);

        $resEditor = $this->statefulJson('GET', '/api/members/'.$editor->id)
            ->assertOk()
            ->assertJsonPath('member.name', 'Editor Person');
        $placesEd = $resEditor->json('places');
        $this->assertIsArray($placesEd);
        $this->assertCount(1, $placesEd);
        $this->assertSame($place->id, $placesEd[0]['id']);
    }

    public function test_member_profile_resolves_by_username(): void
    {
        $member = User::factory()->create([
            'username' => 'member_handle',
        ]);
        $viewer = User::factory()->create();

        $this->actingAs($viewer);

        $this->statefulJson('GET', '/api/members/member_handle')
            ->assertOk()
            ->assertJsonPath('member.id', $member->id)
            ->assertJsonPath('member.username', 'member_handle');
    }

    public function test_member_profile_resolves_by_profile_slug(): void
    {
        $member = User::factory()->create(['username' => null]);
        $slug = $member->fresh()->profile_slug;
        $this->assertNotNull($slug);
        $viewer = User::factory()->create();

        $this->actingAs($viewer);

        $this->statefulJson('GET', '/api/members/'.$slug)
            ->assertOk()
            ->assertJsonPath('member.id', $member->id)
            ->assertJsonPath('member.profile_slug', $slug);
    }

    public function test_stranger_can_view_place_details(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $place = Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Public View Place',
            'description' => null,
            'tags' => null,
            'latitude' => 52.5,
            'longitude' => 13.4,
            'location_type' => Place::LOCATION_POINT,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
        ]);

        $this->actingAs($stranger);

        $this->statefulJson('GET', '/api/places/'.$place->id)
            ->assertOk()
            ->assertJsonPath('place.name', 'Public View Place')
            ->assertJsonPath('place.viewer_place_role', '');
    }
}
