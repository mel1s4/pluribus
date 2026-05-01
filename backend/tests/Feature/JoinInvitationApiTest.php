<?php

namespace Tests\Feature;

use App\Mail\JoinInvitationEmailVerificationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityInvitationEmailVerification;
use App\Models\User;
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
            ->assertJsonPath('email', 'invited-one@example.com');

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
}
