<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\User;
use App\Support\LocaleOptions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CommunitySingletonApiTest extends TestCase
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

    public function test_guest_can_get_public_community_branding(): void
    {
        Community::current()->update(['name' => 'Riverbend Commons']);

        $this->withHeaders($this->statefulHeaders())
            ->getJson('/api/community/branding')
            ->assertOk()
            ->assertJsonPath('community.name', 'Riverbend Commons')
            ->assertJsonPath('community.default_language', LocaleOptions::default())
            ->assertJsonStructure(['community' => ['name', 'logo_url', 'default_language', 'currency_code']]);
    }

    public function test_authenticated_member_can_get_community(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($member);

        $this->statefulJson('GET', '/api/community')
            ->assertOk()
            ->assertJsonStructure(['community' => ['id', 'name', 'description', 'rules', 'logo', 'logo_url', 'default_language', 'currency_code']]);
    }

    public function test_root_can_patch_community(): void
    {
        $root = User::factory()->root()->create();
        $community = Community::current();

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/community', [
            'name' => 'Renamed Community',
            'description' => 'About us',
            'rules' => 'Be kind.',
            'logo' => 'https://example.com/logo.png',
            'default_language' => 'es',
            'currency_code' => 'EUR',
        ])
            ->assertOk()
            ->assertJsonPath('community.name', 'Renamed Community')
            ->assertJsonPath('community.rules', 'Be kind.')
            ->assertJsonPath('community.logo', 'https://example.com/logo.png')
            ->assertJsonPath('community.logo_url', 'https://example.com/logo.png')
            ->assertJsonPath('community.default_language', 'es')
            ->assertJsonPath('community.currency_code', 'EUR');

        $this->assertDatabaseHas('communities', [
            'id' => $community->id,
            'name' => 'Renamed Community',
            'default_language' => 'es',
            'currency_code' => 'EUR',
        ]);
    }

    public function test_admin_cannot_patch_community(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/community', [
            'name' => 'Hacked',
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
        ])->assertForbidden();
    }

    public function test_admin_can_patch_community_currency(): void
    {
        $admin = User::factory()->admin()->create();
        $community = Community::current();

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/community/currency', [
            'currency_code' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('community.currency_code', 'USD');

        $this->assertDatabaseHas('communities', [
            'id' => $community->id,
            'currency_code' => 'USD',
        ]);
    }

    public function test_member_cannot_patch_community_currency(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($member);

        $this->statefulJson('PATCH', '/api/community/currency', [
            'currency_code' => 'EUR',
        ])->assertForbidden();
    }

    public function test_root_can_patch_community_currency_via_dedicated_route(): void
    {
        $root = User::factory()->root()->create();

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/community/currency', [
            'currency_code' => '$',
        ])
            ->assertOk()
            ->assertJsonPath('community.currency_code', '$');
    }

    public function test_patch_community_currency_truncates_to_four_characters(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/community/currency', [
            'currency_code' => 'ABCDE',
        ])
            ->assertOk()
            ->assertJsonPath('community.currency_code', 'ABCD');
    }

    public function test_root_can_upload_community_logo_via_multipart_patch(): void
    {
        $root = User::factory()->root()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lMdx2QAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('logo.png', $png);

        $this->actingAs($root)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patch('/api/community', [
                'name' => 'With Logo',
                'description' => 'About',
                'rules' => 'Rules',
                'default_language' => LocaleOptions::default(),
                'logo_upload' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('community.name', 'With Logo');

        $logo = Community::current()->logo;
        $this->assertNotNull($logo);
        $this->assertStringStartsWith('community/', $logo);

        $this->actingAs($root)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->get('/api/community')
            ->assertOk()
            ->assertJsonStructure(['community' => ['logo_url', 'default_language']]);
    }

    public function test_root_patch_rejects_invalid_default_language(): void
    {
        $root = User::factory()->root()->create();
        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/community', [
            'name' => 'Community',
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => 'fr',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['default_language']);
    }
}
