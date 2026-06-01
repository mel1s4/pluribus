<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityDomain;
use App\Models\CommunityMembership;
use App\Models\User;
use App\Mail\VisitorLoginMail;
use App\Models\VisitorLoginToken;
use App\Support\LocaleOptions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommunityCustomDomainApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function communityHostHeaders(string $host): array
    {
        return [
            'Host' => $host,
            'Origin' => 'http://'.$host,
        ];
    }

    private function statefulJson(string $method, string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($headers)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    private function communityWithDomain(string $host, string $slug = 'river-hub'): Community
    {
        $community = Community::query()->create([
            'name' => 'River Hub',
            'slug' => $slug,
            'description' => 'Test community',
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
        ]);
        CommunityDomain::query()->create([
            'community_id' => $community->id,
            'host' => CommunityDomain::normalizeHost($host),
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        return $community;
    }

    public function test_resolve_host_returns_platform_mode_on_localhost(): void
    {
        $this->getJson('/api/community/resolve-host', $this->communityHostHeaders('localhost'))
            ->assertOk()
            ->assertJsonPath('mode', 'platform')
            ->assertJsonPath('community', null);
    }

    public function test_resolve_host_returns_community_for_custom_domain(): void
    {
        $this->communityWithDomain('river.example');

        $this->getJson('/api/community/resolve-host', $this->communityHostHeaders('river.example'))
            ->assertOk()
            ->assertJsonPath('mode', 'community')
            ->assertJsonPath('community.slug', 'river-hub');
    }

    public function test_resolve_host_uses_host_query_when_request_host_is_api(): void
    {
        $this->communityWithDomain('nuestrachante.vzs.mx', 'nuestra-chante');

        $this->getJson('/api/community/resolve-host?host=nuestrachante.vzs.mx', [
            'Host' => 'chante-api.test',
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('mode', 'community')
            ->assertJsonPath('community.slug', 'nuestra-chante');
    }

    public function test_resolve_host_returns_404_for_unknown_custom_domain(): void
    {
        $this->getJson('/api/community/resolve-host', $this->communityHostHeaders('unknown.example'))
            ->assertNotFound();
    }

    public function test_branding_uses_host_without_slug_header(): void
    {
        $this->communityWithDomain('branded.example', 'branded-co');

        $this->getJson('/api/community/branding', $this->communityHostHeaders('branded.example'))
            ->assertOk()
            ->assertJsonPath('community.name', 'River Hub');
    }

    public function test_visitor_consume_creates_visitor_membership_on_community_host(): void
    {
        Mail::fake();
        $community = $this->communityWithDomain('guest.example', 'guest-co');
        $headers = $this->communityHostHeaders('guest.example');
        $email = 'guest@example.com';

        $this->statefulJson('POST', '/api/visitor-auth/request-link', ['email' => $email], $headers)
            ->assertOk();

        $row = VisitorLoginToken::query()->where('email', $email)->first();
        $this->assertNotNull($row);
        $this->assertSame($community->id, (int) $row->community_id);

        $token = $this->extractTokenFromMail();
        $this->assertNotNull($token);
        $this->statefulJson('POST', '/api/visitor-auth/consume/'.$token, [], $headers)
            ->assertOk();

        $user = User::query()->where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('community_user', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => 'visitor',
        ]);
    }

    public function test_login_with_guest_intent_creates_visitor_membership(): void
    {
        $community = $this->communityWithDomain('login-guest.example', 'login-guest');
        $user = User::factory()->create();
        $headers = $this->communityHostHeaders('login-guest.example');

        $this->statefulJson('POST', '/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'intent' => 'guest',
        ], $headers)
            ->assertOk();

        $this->assertDatabaseHas('community_user', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => 'visitor',
        ]);
    }

    public function test_login_without_membership_on_community_host_is_rejected(): void
    {
        $this->communityWithDomain('login-member.example', 'login-member');
        $user = User::factory()->create();
        $headers = $this->communityHostHeaders('login-member.example');

        $this->statefulJson('POST', '/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_existing_member_login_succeeds_on_community_host(): void
    {
        $community = $this->communityWithDomain('login-ok.example', 'login-ok');
        $user = User::factory()->create();
        CommunityMembership::query()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        $headers = $this->communityHostHeaders('login-ok.example');

        $this->statefulJson('POST', '/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $headers)
            ->assertOk();
    }

    public function test_community_admin_can_update_domains_via_settings_endpoint(): void
    {
        $community = $this->communityWithDomain('old.example', 'domains-co');
        $admin = User::factory()->create(['user_type' => 'admin']);
        CommunityMembership::query()->create([
            'community_id' => $community->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);
        $headers = array_merge(
            $this->communityHostHeaders('localhost'),
            ['X-Community-Slug' => 'domains-co'],
        );

        $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->patchJson('/api/community/domains', [
                'domains' => [
                    ['host' => 'new.example', 'is_primary' => true],
                ],
            ], $headers)
            ->assertOk()
            ->assertJsonPath('community.domains.0.host', 'new.example');

        $this->assertDatabaseMissing('community_domains', ['host' => 'old.example']);
        $this->assertDatabaseHas('community_domains', ['host' => 'new.example', 'community_id' => $community->id]);
    }

    public function test_member_in_another_community_keeps_role_when_guest_in_new_one(): void
    {
        $home = Community::query()->create([
            'name' => 'Home',
            'slug' => 'home-co',
            'default_language' => LocaleOptions::default(),
        ]);
        $guestCommunity = $this->communityWithDomain('other.example', 'other-co');
        $user = User::factory()->create();
        CommunityMembership::query()->create([
            'community_id' => $home->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $headers = $this->communityHostHeaders('other.example');
        $this->statefulJson('POST', '/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'intent' => 'guest',
        ], $headers)->assertOk();

        $this->assertDatabaseHas('community_user', [
            'community_id' => $home->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        $this->assertDatabaseHas('community_user', [
            'community_id' => $guestCommunity->id,
            'user_id' => $user->id,
            'role' => 'visitor',
        ]);
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
}
