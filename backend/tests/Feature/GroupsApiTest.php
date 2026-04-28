<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_group_and_becomes_member(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        Community::current();

        $response = $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/groups', [
                'name' => 'Planning Circle',
                'member_ids' => [$peer->id],
            ])
            ->assertCreated();

        $groupId = (int) $response->json('group.id');
        $this->assertDatabaseHas('groups', ['id' => $groupId, 'owner_id' => $owner->id]);
        $this->assertDatabaseHas('group_members', ['group_id' => $groupId, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('group_members', ['group_id' => $groupId, 'user_id' => $peer->id]);
    }

    public function test_member_can_leave_group_if_not_owner(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();

        $groupId = (int) \DB::table('groups')->insertGetId([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'name' => 'Garden Team',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('group_members')->insert([
            ['group_id' => $groupId, 'user_id' => $owner->id, 'role' => 'owner', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['group_id' => $groupId, 'user_id' => $peer->id, 'role' => 'member', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($peer)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->deleteJson('/api/groups/'.$groupId.'/members/'.$peer->id)
            ->assertOk();

        $this->assertDatabaseMissing('group_members', ['group_id' => $groupId, 'user_id' => $peer->id]);
    }
}

