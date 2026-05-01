<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMemberController extends Controller
{
    public function store(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('update', $chat);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $actor = $request->user();
        $requestedIds = collect($validated['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $existingIds = $chat->members()
            ->whereIn('users.id', $requestedIds->all())
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);

        $idsToAttach = $requestedIds->diff($existingIds)->values();
        if ($idsToAttach->isNotEmpty()) {
            $chat->members()->attach(
                $idsToAttach->mapWithKeys(fn (int $id) => [$id => ['joined_at' => now()]])->all()
            );
        }

        $addedUsers = User::query()
            ->whereIn('id', $idsToAttach->all())
            ->get(['id', 'name']);

        foreach ($addedUsers as $addedUser) {
            $this->createSystemMessage(
                $chat,
                $actor->id,
                ChatMessage::EVENT_MEMBER_ADDED,
                [
                    'actor_id' => (int) $actor->id,
                    'actor_name' => $actor->name,
                    'target_id' => (int) $addedUser->id,
                    'target_name' => $addedUser->name,
                ],
                sprintf('%s added %s', $actor->name, $addedUser->name)
            );
        }

        if ($addedUsers->isNotEmpty()) {
            $chat->touch();
        }

        $chat->load(['members:id,name,avatar_path', 'folder:id,name,icon_emoji,icon_bg_color,parent_id,sort_order,user_id']);

        return response()->json([
            'ok' => true,
            'added_member_ids' => $addedUsers->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'chat' => new ChatResource($chat),
        ], 201);
    }

    public function destroy(Request $request, Chat $chat, User $user): JsonResponse
    {
        $this->authorize('update', $chat);

        if ((int) $chat->owner_id === (int) $user->id) {
            return response()->json([
                'message' => 'Cannot remove the chat owner.',
            ], 422);
        }

        $actor = $request->user();
        $wasMember = $chat->members()->where('users.id', $user->id)->exists();
        if ($wasMember) {
            $chat->members()->detach((int) $user->id);
            $this->createSystemMessage(
                $chat,
                $actor->id,
                ChatMessage::EVENT_MEMBER_REMOVED,
                [
                    'actor_id' => (int) $actor->id,
                    'actor_name' => $actor->name,
                    'target_id' => (int) $user->id,
                    'target_name' => $user->name,
                ],
                sprintf('%s removed %s', $actor->name, $user->name)
            );
            $chat->touch();
        }

        $chat->load(['members:id,name,avatar_path', 'folder:id,name,icon_emoji,icon_bg_color,parent_id,sort_order,user_id']);

        return response()->json([
            'ok' => true,
            'removed' => $wasMember,
            'chat' => new ChatResource($chat),
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function createSystemMessage(Chat $chat, int $actorId, string $eventKey, array $meta, string $body): void
    {
        $chat->messages()->create([
            'user_id' => $actorId,
            'type' => ChatMessage::TYPE_SYSTEM,
            'event_key' => $eventKey,
            'event_meta' => $meta,
            'body' => $body,
        ]);
    }
}
