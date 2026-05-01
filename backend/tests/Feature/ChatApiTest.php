<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_group_chat(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        Community::current();

        $response = $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/chats', [
                'type' => Chat::TYPE_GROUP,
                'title' => 'Garden Ops',
                'member_ids' => [$peer->id],
                'icon_emoji' => '🌱',
                'icon_bg_color' => '#16a34a',
            ])
            ->assertCreated();

        $chatId = (int) $response->json('chat.id');
        $this->assertGreaterThan(0, $chatId);
        $this->assertDatabaseHas('chats', ['id' => $chatId, 'owner_id' => $owner->id]);
        $this->assertDatabaseHas('chat_members', ['chat_id' => $chatId, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('chat_members', ['chat_id' => $chatId, 'user_id' => $peer->id]);
    }

    public function test_member_cannot_create_group_chat_without_other_members(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        Community::current();

        $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/chats', [
                'type' => Chat::TYPE_GROUP,
                'title' => 'Solo',
                'member_ids' => [],
            ])
            ->assertUnprocessable();

        $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/chats', [
                'type' => Chat::TYPE_GROUP,
                'title' => 'Solo',
                'member_ids' => [$owner->id],
            ])
            ->assertUnprocessable();
    }

    public function test_member_can_send_message_to_joined_chat(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $chat = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'type' => Chat::TYPE_GROUP,
            'title' => 'Ops',
        ]);
        $chat->members()->sync([
            $owner->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
        ]);

        $this->actingAs($peer)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/chats/'.$chat->id.'/messages', [
                'body' => 'Hello team',
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Hello team');

        $this->assertDatabaseHas('chat_messages', [
            'chat_id' => $chat->id,
            'user_id' => $peer->id,
            'body' => 'Hello team',
        ]);
    }

    public function test_non_owner_cannot_delete_chat(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $chat = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'type' => Chat::TYPE_GROUP,
            'title' => 'Ops',
        ]);
        $chat->members()->sync([
            $owner->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
        ]);

        $this->actingAs($peer)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->deleteJson('/api/chats/'.$chat->id)
            ->assertForbidden();
    }

    public function test_guest_cannot_poll_chat_updates(): void
    {
        $this->getJson('/api/chats/updates')
            ->assertUnauthorized();
    }

    public function test_member_can_poll_chat_updates_with_cursor_progression(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $chat = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'type' => Chat::TYPE_GROUP,
            'title' => 'Ops',
        ]);
        $chat->members()->sync([
            $owner->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
        ]);
        $chat->messages()->create(['user_id' => $owner->id, 'body' => 'm1']);
        $chat->messages()->create(['user_id' => $peer->id, 'body' => 'm2']);
        $chat->messages()->create(['user_id' => $owner->id, 'body' => 'm3']);

        $response = $this->actingAs($owner)
            ->getJson('/api/chats/updates?since_id=0&limit=2')
            ->assertOk();

        $rows = $response->json('data');
        $this->assertCount(2, $rows);
        $this->assertSame('m1', $rows[0]['message']['body']);
        $this->assertSame('m2', $rows[1]['message']['body']);
        $this->assertGreaterThan(0, (int) $response->json('next_since_id'));

        $nextSinceId = (int) $response->json('next_since_id');
        $next = $this->actingAs($owner)
            ->getJson('/api/chats/updates?since_id='.$nextSinceId.'&limit=50')
            ->assertOk();
        $nextRows = $next->json('data');
        $this->assertCount(1, $nextRows);
        $this->assertSame('m3', $nextRows[0]['message']['body']);
    }

    public function test_member_poll_updates_excludes_unjoined_chats(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $outsider = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();

        $chatA = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'type' => Chat::TYPE_GROUP,
            'title' => 'Visible',
        ]);
        $chatA->members()->sync([
            $owner->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
        ]);
        $chatA->messages()->create(['user_id' => $peer->id, 'body' => 'visible-message']);

        $chatB = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $outsider->id,
            'type' => Chat::TYPE_GROUP,
            'title' => 'Hidden',
        ]);
        $chatB->members()->sync([
            $outsider->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
        ]);
        $chatB->messages()->create(['user_id' => $peer->id, 'body' => 'hidden-message']);

        $response = $this->actingAs($owner)
            ->getJson('/api/chats/updates?since_id=0&limit=100')
            ->assertOk();

        $rows = $response->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame((int) $chatA->id, (int) $rows[0]['chat_id']);
        $this->assertSame('visible-message', $rows[0]['message']['body']);
    }

    public function test_owner_can_add_members_and_system_message_is_created(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $newMember = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $chat = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'type' => Chat::TYPE_DIRECT,
            'title' => 'Ops',
        ]);
        $chat->members()->sync([
            $owner->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
        ]);

        $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/chats/'.$chat->id.'/members', [
                'user_ids' => [$newMember->id],
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('chat_members', [
            'chat_id' => $chat->id,
            'user_id' => $newMember->id,
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'chat_id' => $chat->id,
            'type' => 'system',
            'event_key' => 'member_added',
            'user_id' => $owner->id,
        ]);
    }

    public function test_non_owner_cannot_add_or_remove_chat_members(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $target = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $chat = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'type' => Chat::TYPE_GROUP,
            'title' => 'Ops',
        ]);
        $chat->members()->sync([
            $owner->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
            $target->id => ['joined_at' => now()],
        ]);

        $this->actingAs($peer)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/chats/'.$chat->id.'/members', [
                'user_ids' => [$target->id],
            ])
            ->assertForbidden();

        $this->actingAs($peer)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->deleteJson('/api/chats/'.$chat->id.'/members/'.$target->id)
            ->assertForbidden();
    }

    public function test_owner_can_remove_member_and_system_message_is_created(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $chat = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'type' => Chat::TYPE_GROUP,
            'title' => 'Ops',
        ]);
        $chat->members()->sync([
            $owner->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
        ]);

        $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->deleteJson('/api/chats/'.$chat->id.'/members/'.$peer->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('removed', true);

        $this->assertDatabaseMissing('chat_members', [
            'chat_id' => $chat->id,
            'user_id' => $peer->id,
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'chat_id' => $chat->id,
            'type' => 'system',
            'event_key' => 'member_removed',
            'user_id' => $owner->id,
        ]);
    }

    public function test_updates_include_system_message_fields(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $peer = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $chat = Chat::query()->create([
            'community_id' => $community->id,
            'owner_id' => $owner->id,
            'type' => Chat::TYPE_GROUP,
            'title' => 'Ops',
        ]);
        $chat->members()->sync([
            $owner->id => ['joined_at' => now()],
            $peer->id => ['joined_at' => now()],
        ]);
        $chat->messages()->create([
            'user_id' => $owner->id,
            'type' => 'system',
            'event_key' => 'member_added',
            'event_meta' => [
                'actor_id' => $owner->id,
                'actor_name' => $owner->name,
                'target_id' => $peer->id,
                'target_name' => $peer->name,
            ],
            'body' => $owner->name.' added '.$peer->name,
        ]);

        $response = $this->actingAs($owner)
            ->getJson('/api/chats/updates?since_id=0&limit=50')
            ->assertOk();

        $rows = $response->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('system', $rows[0]['message']['type']);
        $this->assertSame('member_added', $rows[0]['message']['event_key']);
        $this->assertSame($owner->name, $rows[0]['message']['event_meta']['actor_name']);
        $this->assertSame($peer->name, $rows[0]['message']['event_meta']['target_name']);
    }
}
