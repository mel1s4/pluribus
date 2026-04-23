<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProfileExtendedFieldsApiTest extends TestCase
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

    public function test_profile_patch_persists_contact_json_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Pat Profile',
            'email' => 'pat-profile-'.uniqid('', true).'@example.com',
        ]);

        $this->actingAs($user);

        $phones = ['+1 555 0100'];
        $contactEmails = ['alt-contact-'.uniqid('', true).'@example.com'];
        $aliases = ['Pat the Tester'];
        $externalLinks = [
            ['title' => 'Example', 'url' => 'https://example.org'],
        ];

        $response = $this->statefulJson('PATCH', '/api/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => null,
            'phone_numbers' => $phones,
            'contact_emails' => $contactEmails,
            'aliases' => $aliases,
            'external_links' => $externalLinks,
        ]);

        $response->assertOk()
            ->assertJsonPath('user.phone_numbers', $phones)
            ->assertJsonPath('user.contact_emails', $contactEmails)
            ->assertJsonPath('user.aliases', $aliases)
            ->assertJsonPath('user.external_links', $externalLinks);

        $user->refresh();
        $this->assertSame($phones, $user->phone_numbers);
        $this->assertSame($contactEmails, $user->contact_emails);
        $this->assertSame($aliases, $user->aliases);
        $this->assertEquals($externalLinks, $user->external_links);
    }

    public function test_profile_patch_duplicate_username_returns_422_with_message(): void
    {
        User::factory()->create([
            'username' => 'reserved-name',
        ]);

        $actor = User::factory()->create([
            'username' => 'actor-name',
        ]);

        $this->actingAs($actor);

        $response = $this->statefulJson('PATCH', '/api/profile', [
            'name' => $actor->name,
            'email' => $actor->email,
            'username' => 'reserved-name',
            'phone_numbers' => [],
            'contact_emails' => [],
            'aliases' => [],
            'external_links' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);

        $messages = $response->json('errors.username');
        $this->assertIsArray($messages);
        $this->assertStringContainsStringIgnoringCase('taken', (string) ($messages[0] ?? ''));
    }
}
