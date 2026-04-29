<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceAudience;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PlaceOffersCsvApiTest extends TestCase
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

    public function test_export_offers_csv_contains_header_and_rows(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'CSV Spot');
        $place->offers()->create([
            'sku' => 'coffee-1',
            'title' => 'Coffee',
            'description' => 'Fresh',
            'price' => 3.99,
            'visibility_scope' => 'public',
        ]);

        $response = $this->actingAs($user)->get('/api/places/'.$place->id.'/offers/export.csv');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertSee('sku,title,description,price,visibility_scope,audience_keys,tags', false);
        $response->assertSee('coffee-1', false);
    }

    public function test_import_offers_csv_upserts_by_sku_and_reports_partial_errors(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $place = $this->makePlaceForUser($user, 'CSV Spot');
        $audience = PlaceAudience::query()->create(['place_id' => $place->id, 'name' => 'Vips']);
        $place->offers()->create([
            'sku' => 'coffee-1',
            'title' => 'Coffee',
            'description' => 'Old',
            'price' => 2.50,
            'visibility_scope' => 'public',
        ]);

        $csv = implode("\n", [
            'sku,title,description,price,visibility_scope,audience_keys,tags',
            'coffee-1,Coffee Updated,Fresh roast,4.25,public,,"hot,beans"',
            'tea-1,Tea New,Herbal,2.10,audience,Vips,herbal',
            'bad-row,,Missing title,1.00,public,,',
        ]);
        $file = UploadedFile::fake()->createWithContent('offers.csv', $csv);

        $response = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/api/places/'.$place->id.'/offers/import.csv', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('created', 1);
        $response->assertJsonPath('updated', 1);
        $response->assertJsonPath('failed', 1);

        $this->assertDatabaseHas('place_offers', [
            'place_id' => $place->id,
            'sku' => 'coffee-1',
            'title' => 'Coffee Updated',
        ]);
        $this->assertDatabaseHas('place_offers', [
            'place_id' => $place->id,
            'sku' => 'tea-1',
            'title' => 'Tea New',
        ]);
        $this->assertDatabaseMissing('place_offers', [
            'place_id' => $place->id,
            'sku' => 'bad-row',
        ]);

        $tea = $place->offers()->where('sku', 'tea-1')->firstOrFail();
        $this->assertSame('audience', $tea->visibility_scope);
        $this->assertSame([(int) $audience->id], $tea->audiences()->pluck('place_audiences.id')->map(fn ($id) => (int) $id)->all());
    }
}
