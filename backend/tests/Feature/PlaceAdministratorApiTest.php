<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceAdministratorApiTest extends TestCase
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

    public function test_owner_can_add_full_access_and_editor(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $fa = User::factory()->create(['user_type' => 'member']);
        $ed = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner);

        $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/administrators', [
                'user_id' => $fa->id,
                'role' => Place::ADMIN_ROLE_FULL_ACCESS,
            ])
            ->assertCreated();

        $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/places/'.$place->id.'/administrators', [
                'user_id' => $ed->id,
                'role' => Place::ADMIN_ROLE_EDITOR,
            ])
            ->assertCreated();

        $list = $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places/'.$place->id.'/administrators')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $list);
    }

    public function test_editor_cannot_list_administrators_but_can_view_place(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $editor = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner);
        $place->administrators()->attach($editor->id, ['role' => Place::ADMIN_ROLE_EDITOR]);

        $this->actingAs($editor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places/'.$place->id.'/administrators')
            ->assertForbidden();

        $this->actingAs($editor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places/'.$place->id)
            ->assertOk()
            ->assertJsonPath('place.viewer_place_role', Place::ADMIN_ROLE_EDITOR)
            ->assertJsonPath('place.can_manage_admins', false);
    }

    public function test_full_access_admin_can_list_administrators(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $fa = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner);
        $place->administrators()->attach($fa->id, ['role' => Place::ADMIN_ROLE_FULL_ACCESS]);

        $this->actingAs($fa)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places/'.$place->id.'/administrators')
            ->assertOk();
    }

    public function test_place_appears_in_index_for_editor(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $editor = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlace($owner);
        $place->administrators()->attach($editor->id, ['role' => Place::ADMIN_ROLE_EDITOR]);

        $rows = $this->actingAs($editor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/places')
            ->assertOk()
            ->json('data');
        $ids = array_map(fn (array $row): int => (int) ($row['id'] ?? 0), is_array($rows) ? $rows : []);

        $this->assertContains($place->id, $ids);
    }
}
