<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChatMessageController extends Controller
{
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
        $message = $chat->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated()['body'],
        ]);
        $message->load('user:id,name,avatar_path');
        event(new MessageSent($message));

        return response()->json([
            'message' => new ChatMessageResource($message),
        ], 201);
    }
}
