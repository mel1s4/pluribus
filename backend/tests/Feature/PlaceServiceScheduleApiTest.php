<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceServiceScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_place_persists_service_schedule_in_json_response(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);

        $schedule = [
            'mon' => [['open' => '09:00', 'close' => '12:00'], ['open' => '14:00', 'close' => '18:00']],
            'tue' => [],
            'wed' => [],
            'thu' => [],
            'fri' => [],
            'sat' => [],
            'sun' => [],
        ];

        $res = $this->actingAs($user)->postJson('/api/places', [
            'name' => 'Scheduled shop',
            'slug' => 'scheduled-shop',
            'tags' => [],
            'service_schedule' => $schedule,
        ]);

        $res->assertCreated();
        $mon = $res->json('place.service_schedule.mon');
        $this->assertIsArray($mon);
        $this->assertCount(2, $mon);
        $this->assertSame('09:00', $mon[0]['open']);
        $this->assertSame('12:00', $mon[0]['close']);

        $placeId = (int) $res->json('place.id');
        $this->assertDatabaseHas('places', [
            'id' => $placeId,
        ]);
        $stored = Place::query()->findOrFail($placeId);
        $this->assertIsArray($stored->service_schedule);
        $this->assertCount(2, $stored->service_schedule['mon']);
    }

    public function test_owner_can_patch_service_schedule_only(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = Place::query()->create([
            'user_id' => $user->id,
            'name' => 'Venue',
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

        $patch = $this->actingAs($user)->patchJson('/api/places/'.$place->id, [
            'service_schedule' => [
                'mon' => [['open' => '10:00', 'close' => '16:00']],
                'tue' => [],
                'wed' => [],
                'thu' => [],
                'fri' => [],
                'sat' => [],
                'sun' => [],
            ],
        ]);

        $patch->assertOk();
        $patch->assertJsonPath('place.service_schedule.mon.0.open', '10:00');
        $patch->assertJsonPath('place.service_schedule.mon.0.close', '16:00');

        $place->refresh();
        $this->assertIsArray($place->service_schedule);
        $this->assertSame('10:00', $place->service_schedule['mon'][0]['open']);
    }

    public function test_all_empty_slots_store_null_in_database(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);

        $emptyDays = [
            'mon' => [],
            'tue' => [],
            'wed' => [],
            'thu' => [],
            'fri' => [],
            'sat' => [],
            'sun' => [],
        ];

        $res = $this->actingAs($user)->postJson('/api/places', [
            'name' => 'No hours',
            'slug' => 'no-hours',
            'service_schedule' => $emptyDays,
        ]);

        $res->assertCreated();
        $id = (int) $res->json('place.id');
        $row = Place::query()->findOrFail($id);
        $this->assertNull($row->service_schedule);
    }
}
