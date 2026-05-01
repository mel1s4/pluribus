<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Community;
use App\Models\Place;
use App\Models\PlaceOffer;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function attach(User $user, Community $community, string $role = 'member'): void
    {
        $user->communities()->syncWithoutDetaching([
            $community->id => ['role' => $role],
        ]);
    }

    public function test_guest_cannot_list_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_member_can_list_notifications_and_mark_read(): void
    {
        $community = Community::current();
        $member = User::factory()->create(['user_type' => 'member']);
        $this->attach($member, $community);
        $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\WalletIncomingGrantNotification',
            'data' => ['kind' => 'wallet_grant_in', 'title' => 'T', 'body' => 'B', 'action' => []],
        ]);

        $list = $this->actingAs($member)
            ->getJson('/api/notifications?per_page=10')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $list);
        $this->assertGreaterThan(0, count($list['data']));
        $id = (string) $list['data'][0]['id'];
        $this->assertNull($list['data'][0]['read_at'] ?? null);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/notifications/read', ['ids' => [$id]])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $row = DB::table('notifications')->where('id', $id)->first();
        $this->assertNotNull($row?->read_at);
    }

    public function test_wallet_transfer_creates_notification_for_recipient(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $a = User::factory()->create(['user_type' => 'member', 'email' => 'notif-wallet-a@example.com']);
        $b = User::factory()->create(['user_type' => 'member', 'email' => 'notif-wallet-b@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($a, $community);
        $this->attach($b, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'notif-wallet-a@example.com',
                'amount' => 50,
            ])
            ->assertOk();

        $this->actingAs($a)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/transfer', [
                'community_id' => $community->id,
                'recipient_email' => 'notif-wallet-b@example.com',
                'amount' => 10,
            ])
            ->assertOk();

        $count = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $b->id)
            ->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_wallet_grant_creates_notification_for_recipient(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $member = User::factory()->create(['user_type' => 'member', 'email' => 'notif-grant@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($member, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'notif-grant@example.com',
                'amount' => 12.5,
            ])
            ->assertOk();

        $this->assertGreaterThanOrEqual(
            1,
            DB::table('notifications')->where('notifiable_id', $member->id)->count()
        );
    }

    public function test_chat_message_notifies_other_members(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $chat = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'type' => Chat::TYPE_GROUP,
            'title' => 'Team',
        ]);
        $chat->members()->sync([
            $owner->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
        ]);

        $before = DB::table('notifications')->where('notifiable_id', $owner->id)->count();

        $this->actingAs($peer)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/chats/'.$chat->id.'/messages', [
                'body' => 'Ping from peer',
            ])
            ->assertCreated();

        $after = DB::table('notifications')->where('notifiable_id', $owner->id)->count();
        $this->assertSame($before + 1, $after);
    }

    public function test_order_notifies_place_owner(): void
    {
        $visitor = User::factory()->visitor()->create();
        $owner = User::factory()->create(['user_type' => 'member']);
        $place = Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Notif Cafe',
            'slug' => 'notif-cafe-'.uniqid(),
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
        $offer = PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'Tea',
            'description' => null,
            'price' => 2.00,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);

        $this->actingAs($visitor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/cart/items', [
                'place_offer_id' => $offer->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->actingAs($visitor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/orders')
            ->assertCreated();

        $n = DB::table('notifications')
            ->where('notifiable_id', $owner->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($n);
        $data = json_decode((string) $n->data, true);
        $this->assertIsArray($data);
        $this->assertSame('place_new_order', $data['kind'] ?? null);
    }

    public function test_place_order_status_change_notifies_buyer(): void
    {
        $visitor = User::factory()->visitor()->create();
        $owner = User::factory()->create(['user_type' => 'member']);
        $place = Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Status Cafe',
            'slug' => 'status-cafe-'.uniqid(),
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
        $offer = PlaceOffer::query()->create([
            'place_id' => $place->id,
            'title' => 'Milk',
            'description' => null,
            'price' => 1.00,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);

        $this->actingAs($visitor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/cart/items', [
                'place_offer_id' => $offer->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $orderId = (int) $this->actingAs($visitor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/orders')
            ->assertCreated()
            ->json('order.id');

        $before = DB::table('notifications')->where('notifiable_id', $visitor->id)->count();

        $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson("/api/places/{$place->id}/orders/{$orderId}", [
                'status' => 'confirmed',
            ])
            ->assertOk();

        $after = DB::table('notifications')->where('notifiable_id', $visitor->id)->count();
        $this->assertSame($before + 1, $after);
    }

    public function test_mark_all_read(): void
    {
        $community = Community::current();
        $member = User::factory()->create(['user_type' => 'member']);
        $this->attach($member, $community);
        $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\WalletIncomingGrantNotification',
            'data' => ['kind' => 'x', 'title' => 'T', 'body' => 'B'],
        ]);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $unread = DB::table('notifications')
            ->where('notifiable_id', $member->id)
            ->whereNull('read_at')
            ->count();
        $this->assertSame(0, $unread);
    }
}
