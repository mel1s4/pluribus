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

    /**
     * @param  array<string, string>  $extraHeaders
     */
    private function statefulJson(string $method, string $uri, array $data = [], array $extraHeaders = []): TestResponse
    {
        return $this->withHeaders(array_merge($this->statefulHeaders(), $extraHeaders))
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    /**
     * @return array<string, string>
     */
    private function slugHeaders(Community $community): array
    {
        $slug = (string) $community->slug;
        if ($slug === '') {
            $slug = 'community';
            $community->forceFill(['slug' => $slug])->save();
        }

        return ['X-Community-Slug' => $slug];
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

        $community = Community::current();
        $admin = User::factory()->admin()->create();
        $admin->communities()->syncWithoutDetaching([$community->id => ['role' => 'admin']]);
        $this->actingAs($admin);

        $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => 4,
        ], $this->slugHeaders($community))->assertCreated()
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
        $this->assertStringContainsString('/join-invitation-share/', $joinUrl);
        $this->assertStringContainsString('redirect_locale=es', $joinUrl);
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
        $this->assertStringContainsString('/join-invitation-share/', $joinUrl);
        $this->assertStringContainsString('redirect_locale=en', $joinUrl);
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
        $this->assertStringContainsString('/join-invitation-share/', $joinUrl);
        $this->assertStringContainsString('redirect_locale=es', $joinUrl);
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
        $this->assertStringContainsString('/join-invitation-share/', $joinUrl);
        $this->assertStringContainsString('redirect_locale=en', $joinUrl);
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

    public function test_unlimited_link_invitation_can_set_auto_grant_and_cap(): void
    {
        Mail::fake();

        $root = User::factory()->root()->create();
        $this->actingAs($root);

        $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => null,
            'grant_credits' => 12.5,
            'grant_limit_uses' => 3,
        ])->assertCreated()
            ->assertJsonPath('invitation.grant_credits', '12.50')
            ->assertJsonPath('invitation.grant_limit_uses', 3)
            ->assertJsonPath('invitation.grant_uses_count', 0)
            ->assertJsonPath('invitation.grant_max_mint_total', '37.50');
    }

    public function test_limited_invitation_rejects_grant_limit_uses(): void
    {
        Mail::fake();

        $root = User::factory()->root()->create();
        $this->actingAs($root);

        $this->statefulJson('POST', '/api/invitations', [
            'max_uses' => 2,
            'grant_credits' => 5,
            'grant_limit_uses' => 1,
        ])->assertStatus(422);
    }
}
