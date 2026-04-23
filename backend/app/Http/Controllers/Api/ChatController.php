<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatRequest;
use App\Http\Requests\UpdateChatRequest;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChatController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $chats = Chat::query()
            ->visibleToUser((int) $user->id)
            ->with(['members:id,name,avatar_path', 'folder:id,name,parent_id,sort_order,user_id'])
            ->latest('updated_at')
            ->get();

        return ChatResource::collection($chats);
    }

    public function store(StoreChatRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $memberIds = collect($validated['member_ids'])
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

        $chat->load(['members:id,name,avatar_path', 'folder:id,name,parent_id,sort_order,user_id']);

        return response()->json([
            'chat' => new ChatResource($chat),
        ], 201);
    }

    public function show(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);
        $chat->load(['members:id,name,avatar_path', 'folder:id,name,parent_id,sort_order,user_id']);

        return response()->json([
            'chat' => new ChatResource($chat),
        ]);
    }

    public function update(UpdateChatRequest $request, Chat $chat): JsonResponse
    {
        $this->authorize('update', $chat);
        $chat->fill($request->validated());
        $chat->save();
        $chat->load(['members:id,name,avatar_path', 'folder:id,name,parent_id,sort_order,user_id']);

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
}
