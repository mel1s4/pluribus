<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\ChatRealtimeDriver;
use App\Support\ChatSse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatSseController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return response()->stream(function () use ($user): void {
            if (function_exists('ini_set')) {
                @ini_set('output_buffering', 'off');
                @ini_set('zlib.output_compression', '0');
            }
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $write = static function (string $chunk): void {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $write(": ping\n\n");

            try {
                if (ChatRealtimeDriver::resolve() === 'redis') {
                    $this->streamRedis($user, $write);
                } else {
                    $this->streamDatabase($user, $write);
                }
            } catch (Throwable $e) {
                report($e);
                if (! connection_aborted()) {
                    $write("event: error\ndata: {\"error\":\"sse\"}\n\n");
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function streamRedis(User $user, callable $write): void
    {
        $channel = ChatSse::userChannel((int) $user->id);

        Redis::connection('subscribe')->subscribe([$channel], function (string $message) use ($write): void {
            if (connection_aborted()) {
                return;
            }
            $write('data: '.$message."\n\n");
        });
    }

    private function streamDatabase(User $user, callable $write): void
    {
        $chatIds = DB::table('chat_members')
            ->where('user_id', $user->id)
            ->orderBy('chat_id')
            ->limit(100)
            ->pluck('chat_id')
            ->all();

        $pollSeconds = max(1, min(60, (int) config('chat.database_poll_seconds', 2)));

        if ($chatIds === []) {
            while (! connection_aborted()) {
                sleep($pollSeconds);
                $write(": ping\n\n");
            }

            return;
        }

        $lastId = (int) ChatMessage::query()->whereIn('chat_id', $chatIds)->max('id');
        $lastPingAt = time();

        while (! connection_aborted()) {
            sleep($pollSeconds);

            $messages = ChatMessage::query()
                ->whereIn('chat_id', $chatIds)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(50)
                ->with('user:id,name,avatar_path')
                ->get();

            foreach ($messages as $message) {
                if (connection_aborted()) {
                    return;
                }
                $payload = json_encode([
                    'chat_id' => $message->chat_id,
                    'message' => (new ChatMessageResource($message))->resolve(),
                ]);
                if ($payload !== false) {
                    $write('data: '.$payload."\n\n");
                }
                $lastId = $message->id;
            }

            if (time() - $lastPingAt >= 25) {
                $write(": ping\n\n");
                $lastPingAt = time();
            }
        }
    }
}
