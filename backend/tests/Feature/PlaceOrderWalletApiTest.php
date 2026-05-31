<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Order;
use App\Models\Place;
use App\Models\PlaceOffer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FundsCommunityWallet;
use Tests\TestCase;

class PlaceOrderWalletApiTest extends TestCase
{
    use FundsCommunityWallet;
    use RefreshDatabase;

    private function statefulJson(string $method, string $uri, array $data = [])
    {
        return $this->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    private function createPlace(User $owner, string $name = 'Cafe'): Place
    {
        return Place::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => strtolower($name).'-'.uniqid(),
            'is_public' => true,
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

    public function test_checkout_debits_buyer_and_credits_place_wallet(): void
    {
        $community = Community::current();
        $buyer = User::factory()->create();
        $owner = User::factory()->create();
        $place = $this->createPlace($owner);
        $offer = PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'Item',
            'description' => null,
            'price' => 12.50,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);

        $this->fundMemberWallet($buyer, '100.00');
        $this->actingAs($buyer);
        $this->statefulJson('POST', '/api/cart/items', [
            'place_offer_id' => $offer->id,
            'quantity' => 2,
        ])->assertOk();

        $this->statefulJson('POST', '/api/orders', ['notes' => 'Thanks'])
            ->assertCreated()
            ->assertJsonPath('order.payment_method', Order::PAYMENT_COMMUNITY_WALLET)
            ->assertJsonPath('order.total_amount', '25.00');

        $buyerWallet = Wallet::firstOrCreateForMember((int) $community->id, (int) $buyer->id);
        $placeWallet = Wallet::firstOrCreateForPlace((int) $community->id, (int) $place->id);
        $this->assertSame('75.00', (string) $buyerWallet->fresh()->balance);
        $this->assertSame('25.00', (string) $placeWallet->fresh()->balance);

        $this->assertSame(1, WalletLedgerEntry::query()
            ->where('type', WalletLedgerEntry::TYPE_ORDER_SETTLEMENT)
            ->where('actor_kind', WalletLedgerEntry::ACTOR_ORDER_CHECKOUT)
            ->count());
    }

    public function test_checkout_fails_when_insufficient_wallet_balance(): void
    {
        $buyer = User::factory()->create();
        $owner = User::factory()->create();
        $place = $this->createPlace($owner);
        $offer = PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'X',
            'description' => null,
            'price' => 50.00,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);

        $this->fundMemberWallet($buyer, '10.00');
        $this->actingAs($buyer);
        $this->statefulJson('POST', '/api/cart/items', [
            'place_offer_id' => $offer->id,
            'quantity' => 1,
        ])->assertOk();

        $this->statefulJson('POST', '/api/orders')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_checkout_splits_totals_across_two_places(): void
    {
        $community = Community::current();
        $buyer = User::factory()->create();
        $owner = User::factory()->create();
        $a = $this->createPlace($owner, 'A');
        $b = $this->createPlace($owner, 'B');
        $oa = PlaceOffer::query()->create([
            'place_id' => $a->id,
            'title' => 'a1',
            'description' => null,
            'price' => 1.00,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);
        $ob = PlaceOffer::query()->create([
            'place_id' => $b->id,
            'title' => 'b1',
            'description' => null,
            'price' => 2.00,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);

        $this->fundMemberWallet($buyer, '100.00');
        $this->actingAs($buyer);
        $this->statefulJson('POST', '/api/cart/items', ['place_offer_id' => $oa->id, 'quantity' => 3])->assertOk();
        $this->statefulJson('POST', '/api/cart/items', ['place_offer_id' => $ob->id, 'quantity' => 1])->assertOk();

        $this->statefulJson('POST', '/api/orders')->assertCreated();

        $wa = Wallet::firstOrCreateForPlace((int) $community->id, (int) $a->id);
        $wb = Wallet::firstOrCreateForPlace((int) $community->id, (int) $b->id);
        $this->assertSame('3.00', (string) $wa->fresh()->balance);
        $this->assertSame('2.00', (string) $wb->fresh()->balance);

        $this->assertSame(2, WalletLedgerEntry::query()
            ->where('type', WalletLedgerEntry::TYPE_ORDER_SETTLEMENT)
            ->count());
    }

    public function test_place_owner_can_transfer_from_place_wallet_to_member(): void
    {
        $community = Community::current();
        $owner = User::factory()->create();
        $this->ensureCommunityMember($owner);
        $recipient = User::factory()->create(['email' => 'recv@example.com']);
        $this->fundMemberWallet($recipient, '0.00');
        $place = $this->createPlace($owner);
        $pw = Wallet::firstOrCreateForPlace((int) $community->id, (int) $place->id);
        $pw->balance = '40.00';
        $pw->save();

        $this->actingAs($owner);
        $this->statefulJson('POST', "/api/places/{$place->id}/wallet/transfer", [
            'recipient_email' => 'recv@example.com',
            'amount' => 15,
            'note' => 'Payout',
        ])->assertOk();

        $this->assertSame('25.00', (string) $pw->fresh()->balance);
        $rw = Wallet::firstOrCreateForMember((int) $community->id, (int) $recipient->id);
        $this->assertSame('15.00', (string) $rw->fresh()->balance);

        $entryId = (int) WalletLedgerEntry::query()->orderByDesc('id')->value('id');
        $this->assertDatabaseHas('wallet_privileged_audits', [
            'wallet_ledger_entry_id' => $entryId,
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_checkout_requires_community_membership(): void
    {
        $buyer = User::factory()->create(['user_type' => 'member']);
        $owner = User::factory()->create();
        $this->ensureCommunityMember($owner);
        $place = $this->createPlace($owner);
        $offer = PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'X',
            'description' => null,
            'price' => 1.00,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);

        $this->actingAs($buyer);
        $this->statefulJson('POST', '/api/cart/items', [
            'place_offer_id' => $offer->id,
            'quantity' => 1,
        ])->assertOk();

        $this->statefulJson('POST', '/api/orders')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_place_editor_cannot_transfer_from_treasury(): void
    {
        $owner = User::factory()->create();
        $this->ensureCommunityMember($owner);
        $editor = User::factory()->create(['email' => 'editor@example.com']);
        $this->ensureCommunityMember($editor);
        $recipient = User::factory()->create(['email' => 'recv2@example.com']);
        $this->fundMemberWallet($recipient, '0.00');
        $place = $this->createPlace($owner);
        $place->administrators()->attach($editor->id, ['role' => Place::ADMIN_ROLE_EDITOR]);
        $pw = Wallet::firstOrCreateForPlace((int) $community->id, (int) $place->id);
        $pw->balance = '40.00';
        $pw->save();

        $this->actingAs($editor);
        $this->statefulJson('POST', "/api/places/{$place->id}/wallet/transfer", [
            'recipient_email' => 'recv2@example.com',
            'amount' => 5,
        ])->assertForbidden();
    }
}
