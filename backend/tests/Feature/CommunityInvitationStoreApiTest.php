<?php

namespace Tests\Feature;

use App\Mail\CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommunityInvitationStoreApiTest extends TestCase
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

    public function test_email_invitation_ignores_client_max_uses_and_is_single_use(): void
    {
        Mail::fake();

        $root = User::factory()->root()->create();

        $this->actingAs($root);

        $inviteEmail = 'invite-'.uniqid('', true).'@example.com';

        $this->statefulJson('POST', '/api/invitations', [
            'email' => $inviteEmail,
            'max_uses' => 99,
        ])->assertCreated()
            ->assertJsonPath('invitation.max_uses', 1)
            ->assertJsonPath('invitation.email', $inviteEmail);

        $row = CommunityInvitation::query()->where('email', $inviteEmail)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, $row->max_uses);

        Mail::assertSent(CommunityInvitationMail::class);
    }

    public function test_link_invitation_respects_max_uses(): void
    {
        Mail::fake();

        $root = User::factory()->root()->create();

        $this->actingAs($root);

        $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => 7,
        ])->assertCreated()
            ->assertJsonPath('invitation.max_uses', 7)
            ->assertJsonPath('invitation.email', null);

        Mail::assertNothingSent();
    }

    public function test_admin_can_create_link_invitation_via_invitations_manage_capability(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => 4,
        ])->assertCreated()
            ->assertJsonPath('invitation.max_uses', 4)
            ->assertJsonPath('invitation.email', null);

        Mail::assertNothingSent();
    }

    public function test_join_url_uses_spanish_slug_when_community_default_language_is_spanish(): void
    {
        Mail::fake();

        $root = User::factory()->root()->create();
        Community::current()->update(['default_language' => 'es']);
        $this->actingAs($root);

        $response = $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => 7,
        ])->assertCreated();

        $joinUrl = $response->json('invitation.join_url');
        $this->assertIsString($joinUrl);
        $this->assertStringContainsString('/invitacion/', $joinUrl);
        $this->assertStringNotContainsString('/join/', $joinUrl);
    }

    public function test_join_url_uses_join_slug_when_community_default_language_is_english(): void
    {
        Mail::fake();

        $root = User::factory()->root()->create();
        Community::current()->update(['default_language' => 'en']);
        $this->actingAs($root);

        $response = $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => 7,
        ])->assertCreated();

        $joinUrl = $response->json('invitation.join_url');
        $this->assertIsString($joinUrl);
        $this->assertStringContainsString('/join/', $joinUrl);
    }

    public function test_join_url_prefers_join_url_locale_over_community_default(): void
    {
        Mail::fake();

        $root = User::factory()->root()->create();
        Community::current()->update(['default_language' => 'en']);
        $this->actingAs($root);

        $response = $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => 3,
            'join_url_locale' => 'es',
        ])->assertCreated();

        $joinUrl = $response->json('invitation.join_url');
        $this->assertIsString($joinUrl);
        $this->assertStringContainsString('/invitacion/', $joinUrl);
        $this->assertStringNotContainsString('/join/', $joinUrl);
    }

    public function test_join_url_join_url_locale_en_overrides_spanish_community(): void
    {
        Mail::fake();

        $root = User::factory()->root()->create();
        Community::current()->update(['default_language' => 'es']);
        $this->actingAs($root);

        $response = $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => 3,
            'join_url_locale' => 'en',
        ])->assertCreated();

        $joinUrl = $response->json('invitation.join_url');
        $this->assertIsString($joinUrl);
        $this->assertStringContainsString('/join/', $joinUrl);
        $this->assertStringNotContainsString('/invitacion/', $joinUrl);
    }

    public function test_member_cannot_create_invitation(): void
    {
        Mail::fake();

        $member = User::factory()->create(['user_type' => 'member']);
        $this->actingAs($member);

        $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => 2,
        ])->assertForbidden();

        Mail::assertNothingSent();
    }
}
