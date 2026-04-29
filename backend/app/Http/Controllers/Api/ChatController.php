<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatRequest;
use App\Http\Requests\UpdateChatRequest;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $chats = Chat::query()
            ->visibleToUser((int) $user->id)
            ->addSelect([
                'last_message_at' => ChatMessage::query()
                    ->selectRaw('MAX(chat_messages.created_at)')
                    ->whereColumn('chat_messages.chat_id', 'chats.id'),
            ])
            ->addSelect([
                'unread_count' => ChatMessage::query()
                    ->selectRaw('COUNT(*)')
                    ->join('chat_members as current_member', function ($join) use ($user): void {
                        $join->on('current_member.chat_id', '=', 'chat_messages.chat_id')
                            ->where('current_member.user_id', '=', (int) $user->id);
                    })
                    ->whereColumn('chat_messages.chat_id', 'chats.id')
                    ->where('chat_messages.user_id', '!=', (int) $user->id)
                    ->where(function ($query): void {
                        $query->whereNull('current_member.last_read_at')
                            ->orWhereColumn('chat_messages.created_at', '>', 'current_member.last_read_at');
                    }),
            ])
            ->with(['members:id,name,avatar_path', 'folder:id,name,icon_emoji,icon_bg_color,parent_id,sort_order,user_id'])
            ->orderByDesc(DB::raw('COALESCE(last_message_at, chats.updated_at)'))
            ->get();

        return ChatResource::collection($chats);
    }

    public function store(StoreChatRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $memberIds = collect($validated['member_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->push((int) $user->id)
            ->unique()
            ->values();

        $chat = Chat::query()->create([
            'community_id' => Community::current()->id,
            'owner_id' => $user->id,
            'type' => $validated['type'],
            'title' => $validated['title'] ?? null,
            'icon_emoji' => $validated['icon_emoji'] ?? null,
            'icon_bg_color' => $validated['icon_bg_color'] ?? null,
            'folder_id' => $validated['folder_id'] ?? null,
        ]);

        $chat->members()->sync(
            $memberIds->mapWithKeys(fn ($memberId) => [
                $memberId => ['joined_at' => now()],
            ])->all()
        );

        $chat->load(['members:id,name,avatar_path', 'folder:id,name,icon_emoji,icon_bg_color,parent_id,sort_order,user_id']);

        return response()->json([
            'chat' => new ChatResource($chat),
        ], 201);
    }

    public function show(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);
        $chat->load(['members:id,name,avatar_path', 'folder:id,name,icon_emoji,icon_bg_color,parent_id,sort_order,user_id']);

        return response()->json([
            'chat' => new ChatResource($chat),
        ]);
    }

    public function update(UpdateChatRequest $request, Chat $chat): JsonResponse
    {
        $this->authorize('update', $chat);
        $chat->fill($request->validated());
        $chat->save();
        $chat->load(['members:id,name,avatar_path', 'folder:id,name,icon_emoji,icon_bg_color,parent_id,sort_order,user_id']);

        return response()->json([
            'chat' => new ChatResource($chat),
        ]);
    }

    public function destroy(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('delete', $chat);
        $chat->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function markRead(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);
        $chat->members()->updateExistingPivot((int) $request->user()->id, [
            'last_read_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'chat_id' => $chat->id,
            'last_read_at' => now()->toIso8601String(),
        ]);
    }
}
