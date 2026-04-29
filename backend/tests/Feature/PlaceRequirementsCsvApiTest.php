<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceAudience;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PlaceRequirementsCsvApiTest extends TestCase
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

    public function test_export_requirements_csv_contains_header_and_rows(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'CSV Spot');
        $place->requirements()->create([
            'sku' => 'req-123',
            'title' => 'Beans',
            'description' => null,
            'quantity' => 10,
            'unit' => 'kg',
            'recurrence_mode' => 'once',
            'visibility_scope' => 'public',
        ]);

        $response = $this->actingAs($user)->get('/api/places/'.$place->id.'/requirements/export.csv');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertSee('sku,title,description,quantity,unit,recurrence_mode,recurrence_weekdays,visibility_scope,audience_keys,tags,example_offer_sku', false);
        $response->assertSee('req-123', false);
    }

    public function test_import_requirements_csv_upserts_by_sku_and_supports_partial_failures(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'CSV Spot');
        $audience = PlaceAudience::query()->create(['place_id' => $place->id, 'name' => 'Farmers']);
        $offer = $place->offers()->create([
            'sku' => 'offer-sample',
            'title' => 'Sample',
            'description' => null,
            'price' => 1.00,
            'visibility_scope' => 'public',
        ]);
        $place->requirements()->create([
            'sku' => 'req-1',
            'title' => 'Old Req',
            'description' => null,
            'quantity' => 2,
            'unit' => 'kg',
            'recurrence_mode' => 'once',
            'visibility_scope' => 'public',
        ]);

        $csv = implode("\n", [
            'sku,title,description,quantity,unit,recurrence_mode,recurrence_weekdays,visibility_scope,audience_keys,tags,example_offer_sku',
            'req-1,Updated Req,Needs quickly,5.5,kg,weekly,"mon,fri",public,,urgent,offer-sample',
            'req-2,New Req,Every week,2.0,L,weekly,"tue",audience,Farmers,milk,offer-sample',
            'req-3,Bad Req,No weekdays,1,box,weekly,,public,,,',
        ]);
        $file = UploadedFile::fake()->createWithContent('requirements.csv', $csv);

        $response = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/api/places/'.$place->id.'/requirements/import.csv', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('created', 1);
        $response->assertJsonPath('updated', 1);
        $response->assertJsonPath('failed', 1);
        $this->assertDatabaseHas('place_requirements', [
            'place_id' => $place->id,
            'sku' => 'req-1',
            'title' => 'Updated Req',
            'example_place_offer_id' => $offer->id,
        ]);
        $this->assertDatabaseHas('place_requirements', [
            'place_id' => $place->id,
            'sku' => 'req-2',
            'title' => 'New Req',
            'visibility_scope' => 'audience',
        ]);
        $this->assertDatabaseMissing('place_requirements', [
            'place_id' => $place->id,
            'sku' => 'req-3',
        ]);

        $created = $place->requirements()->where('sku', 'req-2')->firstOrFail();
        $this->assertSame([(int) $audience->id], $created->audiences()->pluck('place_audiences.id')->map(fn ($id) => (int) $id)->all());
    }
}
