<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserVotingIdAudit;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserVotingIdApiTest extends TestCase
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

    private function patchUserPayload(User $target): array
    {
        return [
            'name' => $target->name,
            'email' => $target->email,
            'username' => $target->username,
        ];
    }

    public function test_root_sets_voting_id_and_creates_audit_row(): void
    {
        $root = User::factory()->root()->create();
        $target = User::factory()->create(['voting_id' => null]);

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/users/'.$target->id, array_merge($this->patchUserPayload($target), [
            'voting_id' => '123456',
        ]))->assertOk()
            ->assertJsonPath('user.voting_id', '123456');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'voting_id' => '123456']);
        $this->assertDatabaseHas('user_voting_id_audits', [
            'user_id' => $target->id,
            'changed_by_user_id' => $root->id,
            'old_voting_id' => null,
            'new_voting_id' => '123456',
        ]);
    }

    public function test_patch_same_voting_id_does_not_create_second_audit(): void
    {
        $root = User::factory()->root()->create();
        $target = User::factory()->create(['voting_id' => '111111']);

        UserVotingIdAudit::query()->create([
            'user_id' => $target->id,
            'changed_by_user_id' => $root->id,
            'old_voting_id' => null,
            'new_voting_id' => '111111',
        ]);

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/users/'.$target->id, array_merge($this->patchUserPayload($target), [
            'voting_id' => '111111',
        ]))->assertOk();

        $this->assertSame(1, UserVotingIdAudit::query()->where('user_id', $target->id)->count());
    }

    public function test_root_changes_voting_id_logs_old_and_new(): void
    {
        $root = User::factory()->root()->create();
        $target = User::factory()->create(['voting_id' => '100000']);

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/users/'.$target->id, array_merge($this->patchUserPayload($target), [
            'voting_id' => '200000',
        ]))->assertOk();

        $this->assertDatabaseHas('user_voting_id_audits', [
            'user_id' => $target->id,
            'old_voting_id' => '100000',
            'new_voting_id' => '200000',
            'changed_by_user_id' => $root->id,
        ]);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'voting_id' => '200000']);
    }

    public function test_root_clears_voting_id_logs_audit(): void
    {
        $root = User::factory()->root()->create();
        $target = User::factory()->create(['voting_id' => '300000']);

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/users/'.$target->id, array_merge($this->patchUserPayload($target), [
            'voting_id' => null,
        ]))->assertOk()
            ->assertJsonPath('user.voting_id', null);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'voting_id' => null]);
        $this->assertDatabaseHas('user_voting_id_audits', [
            'user_id' => $target->id,
            'old_voting_id' => '300000',
            'new_voting_id' => null,
        ]);
    }

    public function test_duplicate_voting_id_returns_422(): void
    {
        $root = User::factory()->root()->create();
        $holder = User::factory()->create(['voting_id' => '654321']);
        $target = User::factory()->create(['voting_id' => null]);

        $this->actingAs($root);

        $this->statefulJson('PATCH', '/api/users/'.$target->id, array_merge($this->patchUserPayload($target), [
            'voting_id' => '654321',
        ]))->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'voting_id' => null]);
        $this->assertDatabaseHas('users', ['id' => $holder->id, 'voting_id' => '654321']);
    }

    public function test_member_cannot_list_voting_id_audits(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);
        $target = User::factory()->create();

        $this->actingAs($member);

        $this->statefulJson('GET', '/api/users/'.$target->id.'/voting-id-audits')->assertForbidden();
    }

    public function test_root_can_list_voting_id_audits_paginated(): void
    {
        $root = User::factory()->root()->create();
        $target = User::factory()->create(['voting_id' => null]);

        UserVotingIdAudit::query()->create([
            'user_id' => $target->id,
            'changed_by_user_id' => $root->id,
            'old_voting_id' => null,
            'new_voting_id' => '000001',
        ]);

        $this->actingAs($root);

        $this->statefulJson('GET', '/api/users/'.$target->id.'/voting-id-audits?per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('data.0.new_voting_id', '000001')
            ->assertJsonPath('data.0.changed_by.id', $root->id);
    }

    public function test_profile_patch_ignores_voting_id_field(): void
    {
        $user = User::factory()->create([
            'email' => 'voter-ignore-'.uniqid('', true).'@example.com',
            'voting_id' => null,
        ]);

        $this->actingAs($user);

        $this->statefulJson('PATCH', '/api/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => null,
            'voting_id' => '999999',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'voting_id' => null]);
    }
}
