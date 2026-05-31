<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinInvitationSharePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_page_sets_og_tags_and_redirects_to_frontend_join_path(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);
        config(['app.join_share_base_url' => 'https://api.example.test']);

        $community = Community::current();
        $community->update([
            'name' => 'River Valley Co-op',
            'default_language' => 'en',
            'logo' => null,
        ]);

        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('a', 48);
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

        $response = $this->get('/join-invitation-share/'.$plain.'?redirect_locale=en');
        $response->assertOk();
        $response->assertSee('You have been invited to River Valley Co-op', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('https://app.example.test/join/'.$plain, false);
    }

    public function test_share_page_spanish_title_uses_community_default_language(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);

        $community = Community::current();
        $community->update([
            'name' => 'Asamblea Local',
            'default_language' => 'es',
        ]);

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

        $response = $this->get('/join-invitation-share/'.$plain.'?redirect_locale=en');
        $response->assertOk();
        $response->assertSee('Has sido invitado a Asamblea Local', false);
        $response->assertSee('lang="es"', false);
        $response->assertSee('https://app.example.test/join/'.$plain, false);
    }

    public function test_share_page_includes_og_image_when_community_has_logo_url(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);

        $community = Community::current();
        $community->update([
            'name' => 'Logo Club',
            'default_language' => 'en',
            'logo' => 'https://cdn.example.test/logo.png',
        ]);

        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('c', 48);
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

        $response = $this->get('/join-invitation-share/'.$plain);
        $response->assertOk();
        $response->assertSee('property="og:image" content="https://cdn.example.test/logo.png"', false);
    }

    public function test_share_page_redirect_locale_es_uses_invitacion_path(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);

        $community = Community::current();
        $community->update(['default_language' => 'en']);

        $admin = User::factory()->create(['user_type' => 'admin']);
        $plain = str_repeat('d', 48);
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

        $response = $this->get('/join-invitation-share/'.$plain.'?redirect_locale=es');
        $response->assertOk();
        $response->assertSee('https://app.example.test/invitacion/'.$plain, false);
    }
}
