<?php

namespace Tests\Feature;

use App\Mail\JoinInvitationEmailVerificationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityInvitationEmailVerification;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Support\LocaleOptions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JoinInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://localhost:9123'];
    }

    private function statefulJson(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withHeaders($this->statefulHeaders())
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    public function test_guest_can_preview_valid_invitation(): void
    {
        $community = Community::current();
        $community->update(['name' => 'Test Commons']);
        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('b', 48);
        CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => 3,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->statefulJson('GET', '/api/join-invitations/'.$plain)
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('community_name', 'Test Commons')
            ->assertJsonPath('community_slug', $community->slug)
            ->assertJsonPath('community_logo_url', null)
            ->assertJsonPath('uses_remaining', 3)
            ->assertJsonPath('default_language', LocaleOptions::default());
    }

    public function test_preview_includes_default_language_for_spanish_community(): void
    {
        $community = Community::current();
        $community->update(['default_language' => 'es']);
        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('e', 48);
        CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => 3,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->statefulJson('GET', '/api/join-invitations/'.$plain)
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('default_language', 'es');
    }

    public function test_register_increments_uses_after_verified_flow_and_exhausts_single_use_invitation(): void
    {
        Mail::fake();

        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('c', 48);
        $invitation = CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => 1,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify-email', [
            'email' => 'invited-one@example.com',
        ])->assertOk()->assertJsonPath('ok', true);

        $verifyPlain = null;
        Mail::assertSent(JoinInvitationEmailVerificationMail::class, function (JoinInvitationEmailVerificationMail $mail) use (&$verifyPlain): bool {
            if (preg_match('#/verify/([A-Za-z0-9]+)$#', $mail->completionUrl, $m) !== 1) {
                return false;
            }
            $verifyPlain = $m[1];

            return true;
        });
        $this->assertNotNull($verifyPlain);

        $this->statefulJson('GET', '/api/join-invitations/'.$plain.'/verify/'.$verifyPlain)
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('email_verified', true)
            ->assertJsonPath('email', 'invited-one@example.com')
            ->assertJsonPath('community_slug', $community->slug);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify/'.$verifyPlain.'/register', [
            'name' => 'Invited User',
            'password' => 'password-ok-1',
            'password_confirmation' => 'password-ok-1',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'invited-one@example.com');

        $this->assertSame(1, $invitation->fresh()->uses_count);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify-email', [
            'email' => 'invited-two@example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['token']);

        $this->statefulJson('GET', '/api/join-invitations/'.$plain)
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('reason', 'exhausted');
    }

    public function test_register_rejects_email_mismatch_when_invitation_targets_email(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('d', 48);
        CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => 'expected@example.com',
            'max_uses' => null,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify-email', [
            'email' => 'other@example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_second_register_with_same_verify_token_fails(): void
    {
        Mail::fake();

        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('f', 48);
        CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => 2,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify-email', [
            'email' => 'twice@example.com',
        ])->assertOk();

        $verifyPlain = null;
        Mail::assertSent(JoinInvitationEmailVerificationMail::class, function (JoinInvitationEmailVerificationMail $mail) use (&$verifyPlain): bool {
            if (preg_match('#/verify/([A-Za-z0-9]+)$#', $mail->completionUrl, $m) !== 1) {
                return false;
            }
            $verifyPlain = $m[1];

            return true;
        });
        $this->assertNotNull($verifyPlain);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify/'.$verifyPlain.'/register', [
            'name' => 'First',
            'password' => 'password-ok-1',
            'password_confirmation' => 'password-ok-1',
        ])->assertCreated();

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify/'.$verifyPlain.'/register', [
            'name' => 'Second',
            'password' => 'password-ok-2',
            'password_confirmation' => 'password-ok-2',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_verify_email_returns_ok_without_mail_when_user_already_exists(): void
    {
        Mail::fake();

        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('g', 48);
        CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => 1,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        User::factory()->create(['email' => 'taken@example.com']);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify-email', [
            'email' => 'taken@example.com',
        ])->assertOk()->assertJsonPath('ok', true);

        Mail::assertNothingSent();
    }

    public function test_show_verify_invalid_when_verification_expired(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('h', 48);
        $invitation = CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => 1,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $verifyPlain = str_repeat('j', 48);
        CommunityInvitationEmailVerification::query()->create([
            'community_invitation_id' => $invitation->id,
            'email' => 'late@example.com',
            'token_hash' => CommunityInvitationEmailVerification::hashPlainToken($verifyPlain),
            'expires_at' => now()->subHour(),
            'consumed_at' => null,
        ]);

        $this->statefulJson('GET', '/api/join-invitations/'.$plain.'/verify/'.$verifyPlain)
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('reason', 'invalid_verification');
    }

    public function test_verified_register_mints_auto_grant_for_new_member_with_localized_note(): void
    {
        Mail::fake();

        $community = Community::current();
        $community->update(['default_language' => 'es']);
        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('k', 48);
        CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => 1,
            'uses_count' => 0,
            'grant_credits' => '9.50',
            'grant_limit_uses' => null,
            'grant_uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify-email', [
            'email' => 'grant-flow@example.com',
        ])->assertOk()->assertJsonPath('ok', true);

        $verifyPlain = null;
        Mail::assertSent(JoinInvitationEmailVerificationMail::class, function (JoinInvitationEmailVerificationMail $mail) use (&$verifyPlain): bool {
            if (preg_match('#/verify/([A-Za-z0-9]+)$#', $mail->completionUrl, $m) !== 1) {
                return false;
            }
            $verifyPlain = $m[1];

            return true;
        });
        $this->assertNotNull($verifyPlain);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/verify/'.$verifyPlain.'/register', [
            'name' => 'Grant Flow',
            'password' => 'password-ok-1',
            'password_confirmation' => 'password-ok-1',
        ])->assertCreated();

        $member = User::query()->where('email', 'grant-flow@example.com')->firstOrFail();
        $wallet = Wallet::query()
            ->where('community_id', $community->id)
            ->where('user_id', $member->id)
            ->firstOrFail();
        $this->assertSame('9.50', (string) $wallet->balance);

        $tx = WalletLedgerEntry::query()->where('community_id', $community->id)->orderByDesc('id')->firstOrFail();
        $this->assertSame('grant', $tx->type);
        $this->assertSame($wallet->public_ref, $tx->to_public_ref);
        $this->assertSame('Otorgado automaticamente al registrarse como miembro', (string) $tx->note);

        $invitation = CommunityInvitation::query()->where('token_hash', CommunityInvitation::hashPlainToken($plain))->firstOrFail();
        $this->assertSame(1, (int) $invitation->grant_uses_count);
    }

    public function test_unlimited_invitation_grant_cap_stops_minting_but_allows_join(): void
    {
        $community = Community::current();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('m', 48);
        CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => null,
            'uses_count' => 0,
            'grant_credits' => '4.00',
            'grant_limit_uses' => 1,
            'grant_uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $first = User::factory()->create(['user_type' => 'member', 'email' => 'first-cap@example.com']);
        $second = User::factory()->create(['user_type' => 'member', 'email' => 'second-cap@example.com']);

        $this->actingAs($first)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/my-communities/join/'.$plain)
            ->assertOk();

        $this->actingAs($second)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/my-communities/join/'.$plain)
            ->assertOk();

        $w1 = Wallet::query()->where('community_id', $community->id)->where('user_id', $first->id)->firstOrFail();
        $this->assertSame('4.00', (string) $w1->balance);
        $w2 = Wallet::query()->where('community_id', $community->id)->where('user_id', $second->id)->first();
        $this->assertTrue($w2 === null || (string) $w2->balance === '0.00');

        $invitation = CommunityInvitation::query()->where('token_hash', CommunityInvitation::hashPlainToken($plain))->firstOrFail();
        $this->assertSame(2, (int) $invitation->uses_count);
        $this->assertSame(1, (int) $invitation->grant_uses_count);
    }
}
