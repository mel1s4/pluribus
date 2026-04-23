<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlaceLogoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_place_with_logo_multipart(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['user_type' => 'member']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lMdx2QAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('place-logo.png', $png);

        $response = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/api/places', [
                'name' => 'Corner shop',
                'description' => 'A nice corner',
                'tags' => json_encode(['retail', 'corner']),
                'logo' => $file,
            ]);

        $response->assertCreated();
        $placeId = (int) $response->json('place.id');
        $this->assertGreaterThan(0, $placeId);
        $path = $response->json('place.logo_path');
        $this->assertIsString($path);
        $this->assertStringStartsWith('places/'.$placeId.'/', $path);
        $this->assertNotNull($response->json('place.logo_url'));
        $this->assertSame(['retail', 'corner'], $response->json('place.tags'));

        Storage::disk('public')->assertExists($path);
    }

    public function test_owner_can_remove_place_logo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['user_type' => 'member']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lMdx2QAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('logo2.png', $png);

        $create = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/api/places', [
                'name' => 'Shop',
                'logo' => $file,
            ])
            ->assertCreated();

        $placeId = (int) $create->json('place.id');
        $path = $create->json('place.logo_path');
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patch('/api/places/'.$placeId, [
                'name' => 'Shop',
                'location_type' => 'point',
                'latitude' => '52.5',
                'longitude' => '13.4',
                'service_area_type' => 'none',
                'area_geojson' => '',
                'remove_logo' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('place.logo_path', null);

        Storage::disk('public')->assertMissing($path);
    }
}
