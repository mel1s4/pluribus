<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Support\LocaleOptions;
use App\Support\WalletLedger\LedgerAppender;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunityCreditsApiTest extends TestCase
{
    use RefreshDatabase;

    private function attach(User $user, Community $community, string $role = 'member'): void
    {
        $user->communities()->syncWithoutDetaching([
            $community->id => ['role' => $role],
        ]);
    }

    public function test_credits_show_public_returns_normalized_total_and_not_member(): void
    {
        $community = Community::query()->create([
            'name' => 'Credits Co',
            'slug' => 'credits-co',
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
            'currency_code' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $member = User::factory()->create(['user_type' => 'member']);
        $this->attach($member, $community);
        $wallet = Wallet::firstOrCreateForMember((int) $community->id, (int) $member->id);

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

        $this->getJson('/api/communities/credits-co/credits')
            ->assertOk()
            ->assertJsonPath('credits_granted_total', '12.50')
            ->assertJsonPath('is_member', false)
            ->assertJsonPath('community.slug', 'credits-co');
    }

    public function test_credits_show_member_sees_is_member_true(): void
    {
        $community = Community::current();
        $member = User::factory()->create(['user_type' => 'member']);
        $this->attach($member, $community);

        $this->actingAs($member)
            ->getJson('/api/communities/'.$community->slug.'/credits')
            ->assertOk()
            ->assertJsonPath('is_member', true);
    }

    public function test_ledger_export_requires_auth(): void
    {
        $community = Community::current();

        $this->getJson('/api/communities/'.$community->slug.'/credits/ledger-export')
            ->assertUnauthorized();
    }

    public function test_ledger_export_forbidden_for_non_member(): void
    {
        $community = Community::current();
        $outsider = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($outsider)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/communities/'.$community->slug.'/credits/ledger-export')
            ->assertForbidden();
    }

    public function test_ledger_export_ok_for_member_with_ledger_payload(): void
    {
        $community = Community::current();
        $member = User::factory()->create(['user_type' => 'member', 'email' => 'export-credits@example.com']);
        $this->attach($member, $community);
        $wallet = Wallet::firstOrCreateForMember((int) $community->id, (int) $member->id);

        DB::transaction(function () use ($community, $wallet): void {
            app(LedgerAppender::class)->append(
                (int) $community->id,
                WalletLedgerEntry::TYPE_GRANT,
                '4.00',
                null,
                $wallet->public_ref,
                WalletLedgerEntry::ACTOR_COMMUNITY_GRANT,
                null,
            );
        });

        $response = $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->get('/api/communities/'.$community->slug.'/credits/ledger-export');

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $data['export_version']);
        $this->assertArrayHasKey('entries', $data);
        $this->assertArrayHasKey('blocks', $data);
        $this->assertArrayHasKey('wallet_ledger', $data);
        $this->assertArrayHasKey('operator_public_key_b64', $data['wallet_ledger']);
        $this->assertNotEmpty($data['entries']);
    }
}
