<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InAppNotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('notifications.view');

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $query = $user->notifications()->orderByDesc('created_at');
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return InAppNotificationResource::collection(
            $query->paginate($perPage)
        );
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('notifications.view');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
        ]);

        $updated = $user->notifications()
            ->whereIn('id', $validated['ids'])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'marked_count' => $updated,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('notifications.view');

        $updated = $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'marked_count' => $updated,
        ]);
    }
}
