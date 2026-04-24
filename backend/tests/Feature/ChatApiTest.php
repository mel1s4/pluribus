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

    public function test_member_can_create_group_chat_with_only_owner_member_ids(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        Community::current();

        $response = $this->actingAs($owner)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/chats', [
                'type' => Chat::TYPE_GROUP,
                'title' => 'Solo',
                'member_ids' => [],
            ])
            ->assertCreated();

        $chatId = (int) $response->json('chat.id');
        $this->assertDatabaseHas('chat_members', ['chat_id' => $chatId, 'user_id' => $owner->id]);
        $this->assertDatabaseCount('chat_members', 1);
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
}
