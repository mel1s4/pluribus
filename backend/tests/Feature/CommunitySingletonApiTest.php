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
            ->assertJsonStructure([
                'community' => [
                    'name',
                    'logo_url',
                    'default_language',
                    'currency_code',
                    'currency_name',
                    'local_currency_code',
                    'latitude',
                    'longitude',
                ],
            ]);
    }

    public function test_authenticated_member_can_get_community(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($member);

        $this->statefulJson('GET', '/api/community')
            ->assertOk()
            ->assertJsonStructure([
                'community' => [
                    'id',
                    'name',
                    'description',
                    'rules',
                    'terms_markdown',
                    'privacy_policy_markdown',
                    'logo',
                    'logo_url',
                    'default_language',
                    'currency_code',
                    'currency_name',
                    'local_currency_code',
                ],
            ]);
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

    public function test_admin_can_patch_community_legal_documents(): void
    {
        $admin = User::factory()->admin()->create();
        $community = Community::current();

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/community/legal-documents', [
            'terms_markdown' => '# Terms\n\nHello.',
            'privacy_policy_markdown' => 'We respect privacy.',
        ])
            ->assertOk()
            ->assertJsonPath('community.terms_markdown', '# Terms\n\nHello.')
            ->assertJsonPath('community.privacy_policy_markdown', 'We respect privacy.');

        $this->assertDatabaseHas('communities', [
            'id' => $community->id,
            'terms_markdown' => '# Terms\n\nHello.',
            'privacy_policy_markdown' => 'We respect privacy.',
        ]);
    }

    public function test_member_cannot_patch_community_legal_documents(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($member);

        $this->statefulJson('PATCH', '/api/community/legal-documents', [
            'terms_markdown' => 'x',
        ])->assertForbidden();
    }

    public function test_root_can_patch_community_legal_documents(): void
    {
        $root = User::factory()->root()->create();

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/community/legal-documents', [
            'terms_markdown' => 'Root terms',
        ])
            ->assertOk()
            ->assertJsonPath('community.terms_markdown', 'Root terms');
    }

    public function test_patch_community_legal_documents_requires_at_least_one_field(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/community/legal-documents', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_markdown']);
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

    public function test_admin_can_patch_community_currency_name_only(): void
    {
        $admin = User::factory()->admin()->create();
        $community = Community::current();

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/community/currency', [
            'currency_name' => 'Sparkles',
        ])
            ->assertOk()
            ->assertJsonPath('community.currency_name', 'Sparkles');

        $this->assertDatabaseHas('communities', [
            'id' => $community->id,
            'currency_name' => 'Sparkles',
        ]);
    }

    public function test_patch_community_currency_name_truncates_to_sixty_four_characters(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $long = str_repeat('A', 70);
        $this->statefulJson('PATCH', '/api/community/currency', [
            'currency_name' => $long,
        ])
            ->assertOk()
            ->assertJsonPath('community.currency_name', str_repeat('A', 64));
    }

    public function test_admin_can_patch_local_currency_code(): void
    {
        $admin = User::factory()->admin()->create();
        $community = Community::current();

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/community/currency', [
            'local_currency_code' => 'MXN',
        ])
            ->assertOk()
            ->assertJsonPath('community.local_currency_code', 'MXN');

        $this->assertDatabaseHas('communities', [
            'id' => $community->id,
            'local_currency_code' => 'MXN',
        ]);
    }

    public function test_patch_local_currency_code_rejects_invalid_value(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/community/currency', [
            'local_currency_code' => 'GBP',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['local_currency_code']);
    }

    public function test_root_can_patch_local_currency_via_community_update(): void
    {
        $root = User::factory()->root()->create();
        $community = Community::current();

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/community', [
            'name' => $community->name,
            'local_currency_code' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('community.local_currency_code', 'USD');
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
