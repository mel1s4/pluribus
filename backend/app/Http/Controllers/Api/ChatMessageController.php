<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatMessageController extends Controller
{
    public function updates(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 50);

        $chatIds = DB::table('chat_members')
            ->where('user_id', $user->id)
            ->orderBy('chat_id')
            ->limit(200)
            ->pluck('chat_id')
            ->all();

        if ($chatIds === []) {
            return response()->json([
                'data' => [],
                'next_since_id' => $sinceId,
            ]);
        }

        $messages = ChatMessage::query()
            ->whereIn('chat_id', $chatIds)
            ->where('id', '>', $sinceId)
            ->orderBy('id')
            ->limit($limit)
            ->with('user:id,name,avatar_path')
            ->get();

        $nextSinceId = $sinceId;
        $data = [];
        foreach ($messages as $message) {
            $nextSinceId = max($nextSinceId, (int) $message->id);
            $data[] = [
                'chat_id' => (int) $message->chat_id,
                'message' => (new ChatMessageResource($message))->resolve(),
            ];
        }

        return response()->json([
            'data' => $data,
            'next_since_id' => $nextSinceId,
        ]);
    }

    public function index(Request $request, Chat $chat): AnonymousResourceCollection
    {
        $this->authorize('view', $chat);

        $messages = $chat->messages()
            ->with('user:id,name,avatar_path')
            ->oldest('id')
            ->cursorPaginate(50);

        return ChatMessageResource::collection($messages);
    }

    public function store(StoreChatMessageRequest $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);
        $sender = $request->user();
        if ($sender === null) {
            abort(403);
        }
        $message = $chat->messages()->create([
            'user_id' => $sender->id,
            'body' => $request->validated()['body'],
        ]);
        $chat->touch();
        $message->load('user:id,name,avatar_path');

        $preview = Str::limit((string) $message->body, 200, '…');
        $chatTitle = (string) ($chat->title ?? '');
        $memberIds = $chat->members()
            ->where('users.id', '!=', $sender->id)
            ->pluck('users.id');
        foreach ($memberIds as $uid) {
            User::query()->find((int) $uid)?->notify(new ChatMessageReceivedNotification(
                (int) $chat->id,
                $chatTitle,
                (int) $message->id,
                (string) $sender->name,
                $preview,
            ));
        }

        return response()->json([
            'message' => new ChatMessageResource($message),
        ], 201);
    }
}
