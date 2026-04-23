<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityLeadershipApiTest extends TestCase
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

    public function test_guest_cannot_get_leadership(): void
    {
        $this->statefulJson('GET', '/api/community/leadership')->assertUnauthorized();
    }

    public function test_member_gets_leaders_ordered_by_tier_then_name(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);

        User::factory()->admin()->create(['name' => 'Zed Admin']);
        User::factory()->admin()->create(['name' => 'Ann Admin']);
        User::factory()->developer()->create(['name' => 'Dev Person']);
        User::factory()->create([
            'name' => 'Z Root',
            'is_root' => true,
            'user_type' => 'root',
            'username' => 'root_z_'.uniqid(),
        ]);
        User::factory()->create([
            'name' => 'A Root',
            'is_root' => true,
            'user_type' => 'root',
            'username' => 'root_a_'.uniqid(),
        ]);

        $this->actingAs($member);

        $res = $this->statefulJson('GET', '/api/community/leadership')->assertOk();

        $names = collect($res->json('leaders'))->pluck('name')->all();

        $this->assertSame(
            ['A Root', 'Z Root', 'Ann Admin', 'Zed Admin', 'Dev Person'],
            $names,
        );

        $res->assertJsonMissingPath('leaders.0.email');
    }

    public function test_leader_includes_avatar_url_when_path_set(): void
    {
        Storage::fake('public');

        $member = User::factory()->create(['user_type' => 'member']);
        $admin = User::factory()->admin()->create([
            'name' => 'With Avatar',
            'avatar_path' => 'avatars/unit-test.png',
        ]);
        Storage::disk('public')->put($admin->avatar_path, 'fake');

        $this->actingAs($member);

        $response = $this->statefulJson('GET', '/api/community/leadership')->assertOk();
        $url = $response->json('leaders.0.avatar_url');
        $this->assertIsString($url);
        $this->assertStringContainsString('avatars/unit-test.png', $url);
    }

    public function test_root_assigns_developer_type(): void
    {
        $root = User::factory()->root()->create();
        $target = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'user_type' => 'developer',
        ])
            ->assertOk()
            ->assertJsonPath('user.user_type', 'developer');
    }
}
