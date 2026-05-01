<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerBlock;
use App\Models\WalletLedgerEntry;
use App\Support\LocaleOptions;
use App\Support\WalletLedger\WalletLedgerVerifier;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    private function attach(User $user, Community $community, string $role = 'member'): void
    {
        $user->communities()->syncWithoutDetaching([
            $community->id => ['role' => $role],
        ]);
    }

    public function test_member_can_view_wallet_and_balance_in_owner_payload(): void
    {
        $community = Community::current();
        $member = User::factory()->create(['user_type' => 'member']);
        $this->attach($member, $community);

        $response = $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet?community_id='.$community->id)
            ->assertOk();

        $response->assertJsonPath('wallet.balance', '0.00');
        $this->assertNotEmpty($response->json('wallet.public_ref'));
        $this->assertDatabaseHas('wallets', [
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_admin_can_grant_and_member_balance_updates(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $member = User::factory()->create(['user_type' => 'member', 'email' => 'member-wallet-test@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($member, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'member-wallet-test@example.com',
                'amount' => 25.5,
                'note' => 'welcome',
            ])
            ->assertOk();

        $wallet = Wallet::query()->where('community_id', $community->id)->where('user_id', $member->id)->first();
        $this->assertNotNull($wallet);
        $this->assertSame('25.50', (string) $wallet->balance);

        $tx = WalletLedgerEntry::query()->where('community_id', $community->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame('grant', $tx->type);
        $this->assertNull($tx->from_public_ref);
        $this->assertSame($wallet->public_ref, $tx->to_public_ref);
        $this->assertSame('community_grant', $tx->actor_kind);
        $this->assertDatabaseHas('wallet_privileged_audits', [
            'wallet_ledger_entry_id' => $tx->id,
            'actor_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('wallet_ledger_blocks', [
            'community_id' => $community->id,
            'height' => 0,
        ]);
    }

    public function test_member_cannot_grant(): void
    {
        $community = Community::current();
        $member = User::factory()->create(['user_type' => 'member']);
        $this->attach($member, $community);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => $member->email,
                'amount' => 10,
            ])
            ->assertForbidden();
    }

    public function test_member_can_transfer_to_another_member_by_email(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $a = User::factory()->create(['user_type' => 'member', 'email' => 'wallet-a@example.com']);
        $b = User::factory()->create(['user_type' => 'member', 'email' => 'wallet-b@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($a, $community);
        $this->attach($b, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'wallet-a@example.com',
                'amount' => 100,
            ])
            ->assertOk();

        $this->actingAs($a)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/transfer', [
                'community_id' => $community->id,
                'recipient_email' => 'wallet-b@example.com',
                'amount' => 40,
            ])
            ->assertOk();

        $wa = Wallet::query()->where('community_id', $community->id)->where('user_id', $a->id)->first();
        $wb = Wallet::query()->where('community_id', $community->id)->where('user_id', $b->id)->first();
        $this->assertSame('60.00', (string) $wa->balance);
        $this->assertSame('40.00', (string) $wb->balance);

        $transfer = WalletLedgerEntry::query()->where('type', 'transfer')->first();
        $this->assertNotNull($transfer);
        $this->assertSame($wa->public_ref, $transfer->from_public_ref);
        $this->assertSame($wb->public_ref, $transfer->to_public_ref);
        $this->assertSame('member_transfer', $transfer->actor_kind);
    }

    public function test_member_can_fetch_own_wallet_transaction_detail(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $a = User::factory()->create(['user_type' => 'member', 'email' => 'tx-detail-a@example.com']);
        $b = User::factory()->create(['user_type' => 'member', 'email' => 'tx-detail-b@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($a, $community);
        $this->attach($b, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'tx-detail-a@example.com',
                'amount' => 50,
            ])
            ->assertOk();

        $this->actingAs($a)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/transfer', [
                'community_id' => $community->id,
                'recipient_email' => 'tx-detail-b@example.com',
                'amount' => 10,
                'note' => 'detail note',
            ])
            ->assertOk();

        $tx = WalletLedgerEntry::query()->where('type', 'transfer')->firstOrFail();

        $this->actingAs($a)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet/transactions/'.$tx->id.'?community_id='.$community->id)
            ->assertOk()
            ->assertJsonPath('transaction.id', $tx->id)
            ->assertJsonPath('transaction.direction', 'out')
            ->assertJsonPath('transaction.amount', '10.00')
            ->assertJsonPath('transaction.note', 'detail note');

        $this->actingAs($b)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet/transactions/'.$tx->id.'?community_id='.$community->id)
            ->assertOk()
            ->assertJsonPath('transaction.direction', 'in');
    }

    public function test_member_cannot_fetch_transaction_not_involving_their_wallet(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $a = User::factory()->create(['user_type' => 'member', 'email' => 'tx-iso-a@example.com']);
        $b = User::factory()->create(['user_type' => 'member', 'email' => 'tx-iso-b@example.com']);
        $c = User::factory()->create(['user_type' => 'member', 'email' => 'tx-iso-c@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($a, $community);
        $this->attach($b, $community);
        $this->attach($c, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'tx-iso-a@example.com',
                'amount' => 20,
            ])
            ->assertOk();

        $this->actingAs($a)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/transfer', [
                'community_id' => $community->id,
                'recipient_email' => 'tx-iso-b@example.com',
                'amount' => 5,
            ])
            ->assertOk();

        $tx = WalletLedgerEntry::query()->where('type', 'transfer')->firstOrFail();

        $this->actingAs($c)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet/transactions/'.$tx->id.'?community_id='.$community->id)
            ->assertNotFound();
    }

    public function test_transfer_fails_with_insufficient_balance(): void
    {
        $community = Community::current();
        $a = User::factory()->create(['user_type' => 'member', 'email' => 'low-a@example.com']);
        $b = User::factory()->create(['user_type' => 'member', 'email' => 'low-b@example.com']);
        $this->attach($a, $community);
        $this->attach($b, $community);

        $this->actingAs($a)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/transfer', [
                'community_id' => $community->id,
                'recipient_email' => 'low-b@example.com',
                'amount' => 50,
            ])
            ->assertStatus(422);
    }

    public function test_transfer_fails_when_recipient_not_in_community(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $a = User::factory()->create(['user_type' => 'member', 'email' => 'in-community@example.com']);
        $outsider = User::factory()->create(['user_type' => 'member', 'email' => 'outsider@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($a, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'in-community@example.com',
                'amount' => 10,
            ])
            ->assertOk();

        $this->actingAs($a)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/transfer', [
                'community_id' => $community->id,
                'recipient_email' => 'outsider@example.com',
                'amount' => 5,
            ])
            ->assertStatus(422);
    }

    public function test_wallet_rejects_wrong_community_for_non_member(): void
    {
        $c1 = Community::current();
        $c2 = Community::query()->create([
            'name' => 'Second',
            'slug' => 'second-wallet-test-'.uniqid(),
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
        ]);

        $member = User::factory()->create(['user_type' => 'member']);
        $this->attach($member, $c1);

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet?community_id='.$c2->id)
            ->assertForbidden();
    }

    public function test_audit_ledger_is_pseudonymous_and_excludes_balance_and_user_ids(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $member = User::factory()->create(['user_type' => 'member', 'email' => 'audit-member@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($member, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'audit-member@example.com',
                'amount' => 3,
            ])
            ->assertOk();

        $response = $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet/audit-ledger?community_id='.$community->id)
            ->assertOk();

        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertNotEmpty($json['data']);
        $row = $json['data'][0];
        $this->assertArrayHasKey('from_public_ref', $row);
        $this->assertArrayHasKey('to_public_ref', $row);
        $this->assertArrayNotHasKey('balance', $row);
        $this->assertArrayNotHasKey('user_id', $row);
        $this->assertArrayNotHasKey('email', $row);
        $encoded = json_encode($json);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('audit-member@example.com', $encoded);
        $this->assertArrayHasKey('leaf_hash', $row);
        $this->assertArrayHasKey('block', $row);
        $this->assertTrue($row['block']['sealed']);
        $this->assertArrayHasKey('merkle_root', $row['block']);
    }

    public function test_ledger_public_key_and_tip_endpoints(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->attach($admin, $community, 'admin');

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet/ledger/public-key?community_id='.$community->id)
            ->assertOk()
            ->assertJsonPath('algorithm', 'ed25519')
            ->assertJsonStructure(['public_key']);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet/ledger/tip?community_id='.$community->id)
            ->assertOk()
            ->assertJsonPath('tip', null);

        $member = User::factory()->create(['user_type' => 'member', 'email' => 'tip-ledger@example.com']);
        $this->attach($member, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'tip-ledger@example.com',
                'amount' => 7,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet/ledger/tip?community_id='.$community->id)
            ->assertOk()
            ->assertJsonPath('tip.height', 0)
            ->assertJsonStructure([
                'tip' => [
                    'height',
                    'prev_commitment',
                    'merkle_root',
                    'block_commitment',
                    'operator_signature',
                    'first_entry_id',
                    'last_entry_id',
                    'entry_count',
                    'sealed_at',
                ],
            ]);
    }

    public function test_sealed_block_passes_offline_verifier(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $member = User::factory()->create(['user_type' => 'member', 'email' => 'verify-ledger@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($member, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'verify-ledger@example.com',
                'amount' => 11,
            ])
            ->assertOk();

        $block = WalletLedgerBlock::query()->where('community_id', $community->id)->firstOrFail();
        $pub = (string) config('wallet_ledger.public_key');
        $this->assertNotSame('', $pub);
        $this->assertTrue(WalletLedgerVerifier::verifySealedBlock($block, $pub));
        $this->assertSame([], WalletLedgerVerifier::verifyChainLinks((int) $community->id));
    }

    public function test_tampered_ledger_entry_fails_verifier(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $member = User::factory()->create(['user_type' => 'member', 'email' => 'tamper-ledger@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($member, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'tamper-ledger@example.com',
                'amount' => 5,
            ])
            ->assertOk();

        $block = WalletLedgerBlock::query()->where('community_id', $community->id)->firstOrFail();
        $pub = (string) config('wallet_ledger.public_key');
        $this->assertTrue(WalletLedgerVerifier::verifySealedBlock($block, $pub));

        $entryId = (int) WalletLedgerEntry::query()->where('community_id', $community->id)->value('id');
        DB::table('wallet_ledger_entries')->where('id', $entryId)->update(['amount' => '999.99']);

        $block->refresh();
        $this->assertFalse(WalletLedgerVerifier::verifySealedBlock($block, $pub));
    }

    public function test_member_cannot_access_audit_identity(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $member = User::factory()->create(['user_type' => 'member', 'email' => 'id-member@example.com']);
        $this->attach($admin, $community, 'admin');
        $this->attach($member, $community);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'id-member@example.com',
                'amount' => 1,
            ])
            ->assertOk();

        $txId = (int) WalletLedgerEntry::query()->value('id');

        $this->actingAs($member)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet/audit-identity/'.$txId.'?community_id='.$community->id)
            ->assertForbidden();
    }

    public function test_root_can_access_audit_identity(): void
    {
        $community = Community::current();
        $root = User::factory()->root()->create();
        $member = User::factory()->create(['user_type' => 'member', 'email' => 'root-audit@example.com']);
        $this->attach($root, $community, 'member');
        $this->attach($member, $community);

        $this->actingAs($root)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/wallet/grants', [
                'community_id' => $community->id,
                'email' => 'root-audit@example.com',
                'amount' => 2,
            ])
            ->assertOk();

        $txId = (int) WalletLedgerEntry::query()->value('id');

        $this->actingAs($root)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet/audit-identity/'.$txId.'?community_id='.$community->id)
            ->assertOk()
            ->assertJsonPath('to_user_id', $member->id)
            ->assertJsonPath('actor_user_id', $root->id);
    }

    public function test_visitor_cannot_view_wallet(): void
    {
        $community = Community::current();
        $visitor = User::factory()->visitor()->create();
        $this->attach($visitor, $community, 'visitor');

        $this->actingAs($visitor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/wallet?community_id='.$community->id)
            ->assertForbidden();
    }
}
