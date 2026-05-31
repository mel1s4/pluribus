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
use Tests\Concerns\FundsCommunityWallet;
use Tests\TestCase;

class VisitorAndTableOrderingApiTest extends TestCase
{
    use FundsCommunityWallet;
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
        $this->fundMemberWallet($visitor, '100.00');
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

    public function test_table_consume_creates_seating_and_cart_shows_active_table(): void
    {
        $visitor = User::factory()->visitor()->create();
        $owner = User::factory()->create();
        $place = $this->createPlace($owner, true);
        $this->actingAs($owner);
        $table = $this->statefulJson('POST', "/api/places/{$place->id}/tables", ['name' => 'T1'])
            ->assertCreated()
            ->json('table');
        $tableId = (int) $table['id'];
        $url = $this->statefulJson('POST', "/api/places/{$place->id}/tables/{$tableId}/access-links")
            ->assertCreated()
            ->json('access_link.url');
        $plain = basename((string) $url);

        $this->actingAs($visitor);
        $this->statefulJson('POST', '/api/table-access/'.$plain.'/consume')->assertOk();

        $this->assertDatabaseHas('table_seatings', [
            'user_id' => $visitor->id,
            'place_id' => $place->id,
            'table_id' => $tableId,
        ]);

        $this->statefulJson('GET', '/api/cart')
            ->assertOk()
            ->assertJsonPath('active_table.table_id', $tableId)
            ->assertJsonPath('active_table.place_id', $place->id)
            ->assertJsonPath('active_table.table_name', 'T1');
    }

    public function test_delete_table_session_clears_seating_for_place(): void
    {
        $visitor = User::factory()->visitor()->create();
        $owner = User::factory()->create();
        $place = $this->createPlace($owner, true);
        $this->actingAs($owner);
        $table = $this->statefulJson('POST', "/api/places/{$place->id}/tables", ['name' => 'T2'])
            ->assertCreated()
            ->json('table');
        $tableId = (int) $table['id'];
        $url = $this->statefulJson('POST', "/api/places/{$place->id}/tables/{$tableId}/access-links")
            ->assertCreated()
            ->json('access_link.url');
        $plain = basename((string) $url);

        $this->actingAs($visitor);
        $this->statefulJson('POST', '/api/table-access/'.$plain.'/consume')->assertOk();
        $this->assertDatabaseHas('table_seatings', ['user_id' => $visitor->id, 'place_id' => $place->id]);

        $this->statefulJson('DELETE', '/api/table-session')->assertOk();
        $this->assertDatabaseMissing('table_seatings', [
            'user_id' => $visitor->id,
            'place_id' => $place->id,
        ]);
    }

    public function test_place_admin_can_fetch_table_detail(): void
    {
        $owner = User::factory()->create();
        $place = $this->createPlace($owner, true);
        $this->actingAs($owner);
        $table = $this->statefulJson('POST', "/api/places/{$place->id}/tables", ['name' => 'Detail'])
            ->assertCreated()
            ->json('table');
        $tableId = (int) $table['id'];

        $this->statefulJson('GET', "/api/places/{$place->id}/tables/{$tableId}")
            ->assertOk()
            ->assertJsonPath('table.name', 'Detail')
            ->assertJsonPath('seatings', []);
    }

    public function test_place_orders_can_filter_by_offer_tag(): void
    {
        $visitor = User::factory()->visitor()->create();
        $owner = User::factory()->create();
        $place = $this->createPlace($owner, true);
        $kitchen = PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'Soup',
            'description' => null,
            'price' => 3.00,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
            'tags' => ['kitchen'],
        ]);
        $bar = PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'Beer',
            'description' => null,
            'price' => 4.00,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
            'tags' => ['bar'],
        ]);

        $this->actingAs($visitor);
        $this->fundMemberWallet($visitor, '100.00');
        $this->statefulJson('POST', '/api/cart/items', [
            'place_offer_id' => $kitchen->id,
            'quantity' => 1,
        ])->assertOk();
        $this->statefulJson('POST', '/api/cart/items', [
            'place_offer_id' => $bar->id,
            'quantity' => 1,
        ])->assertOk();
        $orderId = (int) $this->statefulJson('POST', '/api/orders')->assertCreated()->json('order.id');

        $this->actingAs($owner);
        $this->statefulJson('GET', "/api/places/{$place->id}/orders?tags[]=kitchen")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orderId);

        $this->statefulJson('GET', "/api/places/{$place->id}/orders?tags[]=nope")
            ->assertOk()
            ->assertJsonCount(0, 'data');
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
