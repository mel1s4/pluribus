<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFavorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserFavoriteController extends Controller
{
    /** Keys aligned with app sidebar nav (see frontend Sidebar.vue). */
    public const ALLOWED_ROUTE_KEYS = [
        'dashboard',
        'users',
        'community-settings',
        'chats',
        'folders',
        'tasks',
        'calendar',
        'posts',
        'my-groups',
        'my-places',
        'orders',
        'map',
        'notifications',
        'profile',
        'settings',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = UserFavorite::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['route_key', 'sort_order']);

        return response()->json([
            'favorites' => $rows->map(fn (UserFavorite $f) => [
                'route_key' => $f->route_key,
                'order' => $f->sort_order,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route_key' => ['required', 'string', 'max:64', Rule::in(self::ALLOWED_ROUTE_KEYS)],
        ]);

        $user = $request->user();
        $routeKey = $validated['route_key'];

        $existing = UserFavorite::query()
            ->where('user_id', $user->id)
            ->where('route_key', $routeKey)
            ->first();

        if ($existing !== null) {
            return response()->json([
                'favorite' => [
                    'route_key' => $existing->route_key,
                    'order' => $existing->sort_order,
                ],
            ], 200);
        }

        $maxOrder = (int) UserFavorite::query()
            ->where('user_id', $user->id)
            ->max('sort_order');

        $favorite = UserFavorite::query()->create([
            'user_id' => $user->id,
            'route_key' => $routeKey,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'favorite' => [
                'route_key' => $favorite->route_key,
                'order' => $favorite->sort_order,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $routeKey): JsonResponse
    {
        if (! in_array($routeKey, self::ALLOWED_ROUTE_KEYS, true)) {
            return response()->json(['message' => 'Invalid route key.'], 422);
        }

        $user = $request->user();
        $deleted = UserFavorite::query()
            ->where('user_id', $user->id)
            ->where('route_key', $routeKey)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Favorite not found.'], 404);
        }

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'favorites' => ['required', 'array', 'min:1'],
            'favorites.*.route_key' => ['required', 'string', 'max:64', Rule::in(self::ALLOWED_ROUTE_KEYS)],
            'favorites.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $payload = $validated['favorites'];

        $routeKeys = array_column($payload, 'route_key');
        if (count($routeKeys) !== count(array_unique($routeKeys))) {
            return response()->json(['message' => 'Duplicate route_key in payload.'], 422);
        }

        $ownedKeys = UserFavorite::query()
            ->where('user_id', $user->id)
            ->pluck('route_key')
            ->all();

        sort($ownedKeys);
        $payloadKeys = $routeKeys;
        sort($payloadKeys);

        if ($ownedKeys !== $payloadKeys) {
            return response()->json(['message' => 'Payload must include every favorite exactly once.'], 422);
        }

        DB::transaction(function () use ($user, $payload): void {
            foreach ($payload as $row) {
                UserFavorite::query()
                    ->where('user_id', $user->id)
                    ->where('route_key', $row['route_key'])
                    ->update(['sort_order' => (int) $row['order']]);
            }
        });

        return $this->index($request);
    }
}
