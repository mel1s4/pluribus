<?php

namespace Tests\Feature;

use App\Mail\VisitorLoginMail;
use App\Models\OrderItem;
use App\Models\Place;
use App\Models\PlaceOffer;
use App\Models\Table;
use App\Models\User;
use App\Models\VisitorLoginToken;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VisitorAndTableOrderingApiTest extends TestCase
{
    use RefreshDatabase;

    private function statefulJson(string $method, string $uri, array $data = [])
    {
        return $this->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    public function test_visitor_magic_link_login_flow_creates_and_authenticates_visitor(): void
    {
        Mail::fake();
        $email = 'visitor@example.com';
        $this->statefulJson('POST', '/api/visitor-auth/request-link', ['email' => $email])->assertOk();

        $user = User::query()->where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('visitor', $user->user_type);
        Mail::assertSent(VisitorLoginMail::class);

        $row = VisitorLoginToken::query()->where('email', $email)->first();
        $this->assertNotNull($row);
        $token = $this->extractTokenFromMail();
        $this->assertNotNull($token);

        $this->statefulJson('POST', '/api/visitor-auth/consume/'.$token)
            ->assertOk()
            ->assertJsonPath('user.email', $email)
            ->assertJsonPath('user.user_type', 'visitor');
    }

    public function test_place_admin_can_create_table_and_resolve_access_link(): void
    {
        $owner = User::factory()->create();
        $place = $this->createPlace($owner, true);
        $this->actingAs($owner);
        $table = $this->statefulJson('POST', "/api/places/{$place->id}/tables", ['name' => 'A1'])
            ->assertCreated()
            ->json('table');
        $this->assertIsArray($table);
        $tableId = (int) $table['id'];

        $created = $this->statefulJson('POST', "/api/places/{$place->id}/tables/{$tableId}/access-links")
            ->assertCreated()
            ->json('access_link.url');
        $this->assertIsString($created);
        $plain = basename((string) $created);
        $this->getJson('/api/table-access/'.$plain)
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('table.id', $tableId);
    }

    public function test_checkout_enforces_table_place_match_and_admin_can_reassign_after_order(): void
    {
        $visitor = User::factory()->visitor()->create();
        $owner = User::factory()->create();
        $place = $this->createPlace($owner, true);
        $otherPlace = $this->createPlace($owner, true, 'Other');
        $table = Table::query()->create(['place_id' => $place->id, 'name' => 'A1']);
        $wrongTable = Table::query()->create(['place_id' => $otherPlace->id, 'name' => 'B1']);
        $offer = PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'Coffee',
            'description' => null,
            'price' => 5.00,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);

        $this->actingAs($visitor);
        $this->statefulJson('POST', '/api/cart/items', [
            'place_offer_id' => $offer->id,
            'quantity' => 1,
            'table_id' => $wrongTable->id,
        ])->assertStatus(422);

        $this->statefulJson('POST', '/api/cart/items', [
            'place_offer_id' => $offer->id,
            'quantity' => 1,
            'table_id' => $table->id,
        ])->assertOk();

        $orderId = (int) $this->statefulJson('POST', '/api/orders')->assertCreated()->json('order.id');
        $item = OrderItem::query()->where('order_id', $orderId)->first();
        $this->assertNotNull($item);
        $this->assertSame($table->id, (int) $item->table_id);

        $replacement = Table::query()->create(['place_id' => $place->id, 'name' => 'A2']);
        $this->actingAs($owner);
        $this->statefulJson('PATCH', "/api/places/{$place->id}/orders/{$orderId}/items/{$item->id}/table", [
            'table_id' => $replacement->id,
        ])->assertOk();
    }

    private function extractTokenFromMail(): ?string
    {
        $found = null;
        Mail::assertSent(VisitorLoginMail::class, function (VisitorLoginMail $mail) use (&$found): bool {
            $found = basename($mail->loginUrl);

            return true;
        });

        return $found;
    }

    private function createPlace(User $owner, bool $public, string $name = 'Cafe'): Place
    {
        return Place::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => strtolower($name).'-'.uniqid(),
            'is_public' => $public,
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
    }
}
