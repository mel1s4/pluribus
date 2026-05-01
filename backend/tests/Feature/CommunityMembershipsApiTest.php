<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommunityMembershipsApiTest extends TestCase
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
        if ($community->slug === null || $community->slug === '') {
            $community->forceFill(['slug' => 'test-community'])->save();
        }

        return ['X-Community-Slug' => (string) $community->slug];
    }

    public function test_guest_cannot_list_memberships(): void
    {
        $community = Community::current();
        $this->statefulJson('GET', '/api/community/memberships', [], $this->slugHeaders($community))
            ->assertUnauthorized();
    }

    public function test_member_cannot_list_memberships(): void
    {
        $community = Community::current();
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->syncWithoutDetaching([$community->id => ['role' => 'member']]);

        $this->actingAs($member);

        $this->statefulJson('GET', '/api/community/memberships', [], $this->slugHeaders($community))
            ->assertForbidden();
    }

    public function test_pivot_admin_requires_community_slug_header(): void
    {
        $community = Community::current();
        $admin = User::factory()->admin()->create();
        $admin->communities()->syncWithoutDetaching([$community->id => ['role' => 'admin']]);

        $this->actingAs($admin);

        $this->statefulJson('GET', '/api/community/memberships')
            ->assertForbidden();
    }

    public function test_pivot_admin_can_list_memberships(): void
    {
        $community = Community::current();
        $admin = User::factory()->admin()->create();
        $admin->communities()->syncWithoutDetaching([$community->id => ['role' => 'admin']]);
        $other = User::factory()->create(['user_type' => 'member']);
        $other->communities()->syncWithoutDetaching([$community->id => ['role' => 'member']]);

        $this->actingAs($admin);

        $this->statefulJson('GET', '/api/community/memberships', [], $this->slugHeaders($community))
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('community_id', $community->id);
    }

    public function test_admin_user_type_with_member_pivot_cannot_list_memberships(): void
    {
        $community = Community::current();
        $admin = User::factory()->admin()->create();
        $admin->communities()->syncWithoutDetaching([$community->id => ['role' => 'member']]);

        $this->actingAs($admin);

        $this->statefulJson('GET', '/api/community/memberships', [], $this->slugHeaders($community))
            ->assertForbidden();
    }

    public function test_root_can_list_memberships_with_slug_header(): void
    {
        $community = Community::current();
        $root = User::factory()->root()->create();

        $this->actingAs($root);

        $this->statefulJson('GET', '/api/community/memberships', [], $this->slugHeaders($community))
            ->assertOk();
    }

    public function test_pivot_admin_can_detach_member(): void
    {
        $community = Community::current();
        $admin = User::factory()->admin()->create();
        $admin->communities()->syncWithoutDetaching([$community->id => ['role' => 'admin']]);
        $target = User::factory()->create(['user_type' => 'member']);
        $target->communities()->syncWithoutDetaching([$community->id => ['role' => 'member']]);

        $this->actingAs($admin);

        $this->statefulJson('DELETE', '/api/community/memberships/'.$target->id, [], $this->slugHeaders($community))
            ->assertNoContent();

        $this->assertFalse($target->communities()->where('communities.id', $community->id)->exists());
    }

    public function test_cannot_remove_last_community_admin(): void
    {
        $community = Community::current();
        $soleAdmin = User::factory()->admin()->create();
        $soleAdmin->communities()->syncWithoutDetaching([$community->id => ['role' => 'admin']]);
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->syncWithoutDetaching([$community->id => ['role' => 'member']]);

        $this->actingAs($soleAdmin);

        $this->statefulJson('DELETE', '/api/community/memberships/'.$soleAdmin->id, [], $this->slugHeaders($community))
            ->assertStatus(422);
    }

    public function test_root_can_attach_member_with_role_via_post(): void
    {
        $community = Community::current();
        $root = User::factory()->root()->create();
        $newUser = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($root);

        $this->statefulJson('POST', '/api/community/memberships', [
            'user_id' => $newUser->id,
            'role' => 'admin',
        ], $this->slugHeaders($community))
            ->assertCreated()
            ->assertJsonPath('member.membership_role', 'admin');

        $this->assertTrue(
            $newUser->communities()->where('communities.id', $community->id)->wherePivot('role', 'admin')->exists()
        );
    }

    public function test_cannot_attach_same_user_twice(): void
    {
        $community = Community::current();
        $root = User::factory()->root()->create();
        $user = User::factory()->create(['user_type' => 'member']);
        $user->communities()->syncWithoutDetaching([$community->id => ['role' => 'member']]);

        $this->actingAs($root);

        $this->statefulJson('POST', '/api/community/memberships', [
            'user_id' => $user->id,
            'role' => 'admin',
        ], $this->slugHeaders($community))
            ->assertStatus(422);
    }
}
