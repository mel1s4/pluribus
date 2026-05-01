<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Http\Resources\ChatMessageResource;
use App\Support\ChatRealtimeDriver;
use App\Support\ChatSse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class PublishChatMessageToSse implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 2;

    public int $timeout = 15;

    public function handle(MessageSent $event): void
    {
        if (ChatRealtimeDriver::resolve() !== 'redis') {
            return;
        }

        $message = $event->message;
        $message->loadMissing('user', 'chat.members');

        try {
            $payload = json_encode([
                'chat_id' => $message->chat_id,
                'message' => (new ChatMessageResource($message))->resolve(),
            ], JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('chat_sse.encode_failed', [
                'chat_id' => $message->chat_id,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($message->chat->members as $member) {
            try {
                Redis::publish(ChatSse::userChannel((int) $member->id), $payload);
            } catch (Throwable $e) {
                Log::warning('chat_sse.redis_publish_failed', [
                    'user_id' => $member->id,
                    'chat_id' => $message->chat_id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
