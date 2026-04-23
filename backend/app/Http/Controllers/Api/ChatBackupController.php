<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatBackup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatBackupController extends Controller
{
    public function index(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);
        $backups = $chat->backups()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get()
            ->map(fn (ChatBackup $backup) => [
                'id' => $backup->id,
                'file_name' => basename($backup->file_path),
                'created_at' => $backup->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['backups' => $backups]);
    }

    public function store(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $messages = $chat->messages()
            ->with('user:id,name')
            ->oldest('id')
            ->get();

        $content = $messages->map(function ($message): string {
            $name = $message->user?->name ?? 'Unknown';
            $time = $message->created_at?->toIso8601String() ?? '';

            return sprintf("[%s] %s: %s", $time, $name, (string) $message->body);
        })->implode(PHP_EOL);

        $filename = sprintf('chat-backups/chat-%d-%s.txt', $chat->id, now()->format('YmdHis'));
        Storage::disk('local')->put($filename, $content);

        $backup = $chat->backups()->create([
            'user_id' => $request->user()->id,
            'file_path' => $filename,
        ]);

        return response()->json([
            'backup' => [
                'id' => $backup->id,
                'file_name' => basename($backup->file_path),
                'download_url' => route('chat-backups.download', ['backup' => $backup->id]),
            ],
        ], 201);
    }

    public function download(Request $request, ChatBackup $backup): StreamedResponse
    {
        $chat = $backup->chat;
        abort_if($chat === null, 404);
        $this->authorize('view', $chat);
        abort_unless((int) $backup->user_id === (int) $request->user()->id, 404);

        return Storage::disk('local')->download(
            $backup->file_path,
            basename($backup->file_path),
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
