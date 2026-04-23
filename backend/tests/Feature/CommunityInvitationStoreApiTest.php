<?php

namespace Tests\Feature;

use App\Mail\CommunityInvitationMail;
use App\Models\CommunityInvitation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    private function statefulJson(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
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
}
