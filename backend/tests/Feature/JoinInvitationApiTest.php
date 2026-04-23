<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function statefulJson(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
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
            ->assertJsonPath('uses_remaining', 3);
    }

    public function test_register_increments_uses_and_exhausts_single_use_invitation(): void
    {
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

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/register', [
            'name' => 'Invited User',
            'email' => 'invited-one@example.com',
            'password' => 'password-ok-1',
            'password_confirmation' => 'password-ok-1',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'invited-one@example.com');

        $this->assertSame(1, $invitation->fresh()->uses_count);

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/register', [
            'name' => 'Second User',
            'email' => 'invited-two@example.com',
            'password' => 'password-ok-2',
            'password_confirmation' => 'password-ok-2',
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

        $this->statefulJson('POST', '/api/join-invitations/'.$plain.'/register', [
            'name' => 'Wrong Email',
            'email' => 'other@example.com',
            'password' => 'password-ok-1',
            'password_confirmation' => 'password-ok-1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
