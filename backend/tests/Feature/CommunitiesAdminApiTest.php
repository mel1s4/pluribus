<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunitiesAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function statefulJson(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['Origin' => 'http://localhost:9123'])
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    public function test_root_and_admin_can_list_communities(): void
    {
        Community::query()->create(['name' => 'Northside', 'slug' => 'northside']);
        Community::query()->create(['name' => 'Southside', 'slug' => 'southside']);

        $root = User::factory()->root()->create();
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($root);
        $this->statefulJson('GET', '/api/communities')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin);
        $this->statefulJson('GET', '/api/communities')->assertOk();

        $this->actingAs($member);
        $this->statefulJson('GET', '/api/communities')->assertForbidden();
    }

    public function test_admin_can_create_community(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->statefulJson('POST', '/api/communities', [
            'name' => 'My New Community',
            'description' => 'Hello',
            'rules' => 'Be kind',
            'currency_code' => 'USD',
        ])->assertCreated()
            ->assertJsonPath('community.slug', 'my-new-community');

        $this->assertDatabaseHas('communities', [
            'name' => 'My New Community',
            'slug' => 'my-new-community',
        ]);
    }

    public function test_admin_can_show_single_community(): void
    {
        $community = Community::query()->create(['name' => 'Gamma', 'slug' => 'gamma']);
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin);
        $this->statefulJson('GET', '/api/communities/'.$community->id)
            ->assertOk()
            ->assertJsonPath('data.slug', 'gamma');

        $this->actingAs($member);
        $this->statefulJson('GET', '/api/communities/'.$community->id)->assertForbidden();
    }

    public function test_admin_can_update_community_and_slug_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        $communityA = Community::query()->create(['name' => 'Alpha', 'slug' => 'alpha']);
        Community::query()->create(['name' => 'Beta', 'slug' => 'beta']);

        $this->actingAs($admin);
        $this->statefulJson('PATCH', '/api/communities/'.$communityA->id, [
            'name' => 'Alpha Prime',
            'slug' => 'alpha-prime',
            'description' => 'Updated',
            'rules' => null,
            'logo' => null,
        ])->assertOk()
            ->assertJsonPath('community.slug', 'alpha-prime');

        $this->statefulJson('PATCH', '/api/communities/'.$communityA->id, [
            'name' => 'Alpha Prime',
            'slug' => 'beta',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }
}
