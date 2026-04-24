<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommunityInvitationManageApiTest extends TestCase
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

    public function test_member_cannot_list_invitations(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);
        $this->actingAs($member);

        $this->statefulJson('GET', '/api/invitations')
            ->assertForbidden();
    }

    public function test_admin_can_list_invitations_for_current_community(): void
    {
        $community = Community::current();
        $admin = User::factory()->admin()->create();
        $plain = str_repeat('c', 48);
        $invitation = CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => 'invitee@example.com',
            'max_uses' => 1,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->actingAs($admin);

        $this->statefulJson('GET', '/api/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invitation->id)
            ->assertJsonPath('data.0.kind', 'email')
            ->assertJsonPath('data.0.email', 'invitee@example.com')
            ->assertJsonPath('data.0.has_been_used', false)
            ->assertJsonPath('data.0.is_usable', true);
    }

    public function test_admin_can_delete_invitation(): void
    {
        $community = Community::current();
        $admin = User::factory()->admin()->create();
        $plain = str_repeat('d', 48);
        $invitation = CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => 5,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->actingAs($admin);

        $this->statefulJson('DELETE', '/api/invitations/'.$invitation->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('community_invitations', ['id' => $invitation->id]);
    }

    public function test_member_cannot_delete_invitation(): void
    {
        $community = Community::current();
        $admin = User::factory()->admin()->create();
        $plain = str_repeat('e', 48);
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

        $member = User::factory()->create(['user_type' => 'member']);
        $this->actingAs($member);

        $this->statefulJson('DELETE', '/api/invitations/'.$invitation->id)
            ->assertForbidden();
    }

    public function test_delete_invitation_from_other_community_is_not_found(): void
    {
        $home = Community::current();
        $other = Community::query()->create([
            'name' => 'Other',
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => 'en',
        ]);

        $admin = User::factory()->admin()->create();
        $plain = str_repeat('f', 48);
        $invitation = CommunityInvitation::query()->create([
            'community_id' => $other->id,
            'created_by' => $admin->id,
            'token_hash' => CommunityInvitation::hashPlainToken($plain),
            'email' => null,
            'max_uses' => 1,
            'uses_count' => 0,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->assertSame($home->id, Community::current()->id);

        $this->actingAs($admin);

        $this->statefulJson('DELETE', '/api/invitations/'.$invitation->id)
            ->assertNotFound();
    }
}
