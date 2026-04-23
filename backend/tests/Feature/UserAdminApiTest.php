<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminApiTest extends TestCase
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

    public function test_member_can_list_users(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($user);

        $this->statefulJson('GET', '/api/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_admin_can_list_users(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user);

        $this->statefulJson('GET', '/api/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_member_cannot_create_user(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($user);

        $this->statefulJson('POST', '/api/users', [
            'name' => 'New Person',
            'email' => 'new-person-'.uniqid('', true).'@example.com',
            'password' => 'secret-pass-1',
        ])->assertForbidden();
    }

    public function test_admin_cannot_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $email = 'created-'.uniqid('', true).'@example.com';

        $this->statefulJson('POST', '/api/users', [
            'name' => 'Created User',
            'email' => $email,
            'password' => 'long-password-1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_member_cannot_delete_user(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);
        $target = User::factory()->create();

        $this->actingAs($member);

        $this->statefulJson('DELETE', '/api/users/'.$target->id)->assertForbidden();
    }

    public function test_admin_cannot_delete_other_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);

        $this->statefulJson('DELETE', '/api/users/'.$target->id)->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->statefulJson('DELETE', '/api/users/'.$admin->id)->assertForbidden();
    }

    public function test_root_can_delete_non_root_user(): void
    {
        $root = User::factory()->root()->create();
        $target = User::factory()->create();

        $this->actingAs($root);

        $this->statefulJson('DELETE', '/api/users/'.$target->id)->assertOk();
    }

    public function test_cannot_delete_last_root(): void
    {
        $root = User::factory()->root()->create();

        $this->actingAs($root);

        $this->statefulJson('DELETE', '/api/users/'.$root->id)->assertForbidden();
    }

    public function test_member_can_show_user(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);
        $target = User::factory()->create();

        $this->actingAs($member);

        $this->statefulJson('GET', '/api/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('user.id', $target->id)
            ->assertJsonPath('user.email', $target->email);
    }

    public function test_admin_can_show_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);

        $this->statefulJson('GET', '/api/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('user.id', $target->id)
            ->assertJsonPath('user.email', $target->email);
    }

    public function test_member_cannot_update_user(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);
        $target = User::factory()->create();

        $this->actingAs($member);

        $this->statefulJson('PATCH', '/api/users/'.$target->id, [
            'name' => 'Updated Name',
            'email' => $target->email,
        ])->assertForbidden();
    }

    public function test_admin_cannot_update_non_root_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin);

        $newEmail = 'patched-'.uniqid('', true).'@example.com';

        $this->statefulJson('PATCH', '/api/users/'.$target->id, [
            'name' => 'New Display',
            'email' => $newEmail,
            'username' => null,
        ])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Old Name',
            'email' => $target->email,
        ]);
    }

    public function test_admin_cannot_update_root_user(): void
    {
        $admin = User::factory()->admin()->create();
        $rootTarget = User::factory()->root()->create();

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/users/'.$rootTarget->id, [
            'name' => 'Should Fail',
            'email' => $rootTarget->email,
        ])->assertForbidden();
    }

    public function test_root_can_update_root_peer(): void
    {
        $rootActor = User::factory()->root()->create();
        $peer = User::factory()->create([
            'is_root' => true,
            'user_type' => 'root',
            'username' => 'peer_'.uniqid('', true),
        ]);

        $this->actingAs($rootActor);

        $this->statefulJson('PATCH', '/api/users/'.$peer->id, [
            'name' => 'Peer Renamed',
            'email' => $peer->email,
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Peer Renamed');
    }

    public function test_admin_cannot_patch_user_to_change_type(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($admin);

        $this->statefulJson('PATCH', '/api/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'user_type' => 'admin',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'user_type' => 'member',
        ]);
    }

    public function test_root_can_promote_member_to_admin(): void
    {
        $root = User::factory()->root()->create();
        $target = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'user_type' => 'admin',
        ])
            ->assertOk()
            ->assertJsonPath('user.user_type', 'admin');
    }
}
