<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Place;
use App\Models\PlaceOffer;
use App\Models\Post;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Support\LocaleOptions;
use App\Support\WalletLedger\LedgerAppender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunityMicrositeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_microsite_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/communities/no-such-slug/microsite')
            ->assertNotFound();
    }

    public function test_microsite_public_shape_without_auth(): void
    {
        $community = Community::query()->create([
            'name' => 'River Hub',
            'slug' => 'river-hub',
            'description' => 'We circulate care.',
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
            'currency_code' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $owner = User::factory()->create(['user_type' => 'member']);
        $place = Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Open Cafe',
            'slug' => 'open-cafe',
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
        PlaceOffer::query()->create([
            'place_id' => $place->id,
            'sku' => 'sku-1',
            'title' => 'Coffee',
            'description' => null,
            'price' => '1.00',
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);

        $grantee = User::factory()->create(['user_type' => 'member']);
        $grantee->communities()->attach($community->id, ['role' => 'member']);
        $wallet = Wallet::firstOrCreateForMember((int) $community->id, (int) $grantee->id);

        DB::transaction(function () use ($community, $wallet): void {
            app(LedgerAppender::class)->append(
                (int) $community->id,
                WalletLedgerEntry::TYPE_GRANT,
                '12.50',
                null,
                $wallet->public_ref,
                WalletLedgerEntry::ACTOR_COMMUNITY_GRANT,
                null,
            );
        });

        $res = $this->getJson('/api/communities/river-hub/microsite')
            ->assertOk();

        $res->assertJsonPath('is_member', false);
        $res->assertJsonPath('credits_granted_total', '12.50');
        $res->assertJsonPath('community.slug', 'river-hub');
        $res->assertJsonPath('community.name', 'River Hub');
        $res->assertJsonMissingPath('member_places');
        $res->assertJsonMissingPath('recent_posts');

        $data = $res->json();
        $this->assertArrayHasKey('public_places', $data);
        $this->assertCount(1, $data['public_places']);
        $this->assertSame('open-cafe', $data['public_places'][0]['slug']);
    }

    public function test_microsite_member_sees_posts_and_member_places(): void
    {
        $community = Community::query()->create([
            'name' => 'Member Co',
            'slug' => 'member-co',
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
            'currency_code' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->attach($community->id, ['role' => 'member']);

        $owner = User::factory()->create(['user_type' => 'member']);
        $place = Place::query()->create([
            'user_id' => $owner->id,
            'name' => 'Member Store',
            'slug' => 'member-store',
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
        PlaceOffer::query()->create([
            'place_id' => $place->id,
            'sku' => 'sku-2',
            'title' => 'Bread',
            'description' => null,
            'price' => '2.00',
            'photo_path' => null,
            'gallery_paths' => null,
            'tags' => null,
            'visibility_scope' => PlaceOffer::VISIBILITY_SCOPE_PUBLIC,
        ]);

        Post::query()->create([
            'community_id' => $community->id,
            'author_id' => $member->id,
            'type' => Post::TYPE_ANNOUNCEMENT,
            'title' => 'Hello members',
            'description' => null,
            'content_markdown' => null,
            'tags' => null,
            'visibility_scope' => Post::VISIBILITY_COMMUNITY,
        ]);

        $this->actingAs($member)
            ->getJson('/api/communities/member-co/microsite')
            ->assertOk()
            ->assertJsonPath('is_member', true)
            ->assertJsonPath('recent_posts.0.title', 'Hello members')
            ->assertJsonPath('member_places.0.slug', 'member-store');
    }

    public function test_microsite_non_member_has_no_member_sections(): void
    {
        Community::query()->create([
            'name' => 'Closed Co',
            'slug' => 'closed-co',
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
            'currency_code' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
        $outsider = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($outsider)
            ->getJson('/api/communities/closed-co/microsite')
            ->assertOk()
            ->assertJsonPath('is_member', false)
            ->assertJsonMissingPath('member_places')
            ->assertJsonMissingPath('recent_posts');
    }
}
