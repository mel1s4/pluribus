<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatFolderResource;
use App\Models\Chat;
use App\Models\ChatFolder;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ChatFolderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $folders = ChatFolder::query()
            ->visibleToUser((int) $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ChatFolderResource::collection($folders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon_emoji' => ['nullable', 'string', 'max:16'],
            'icon_bg_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'parent_id' => ['nullable', 'exists:chat_folders,id'],
            'shared_group_id' => ['nullable', 'exists:groups,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $sharedGroupId = isset($validated['shared_group_id']) ? (int) $validated['shared_group_id'] : null;
        if ($sharedGroupId !== null) {
            $allowed = Group::query()
                ->visibleToUser((int) $request->user()->id)
                ->whereKey($sharedGroupId)
                ->exists();
            abort_unless($allowed, 422, 'Invalid shared group.');
        }

        $folder = ChatFolder::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'icon_emoji' => $validated['icon_emoji'] ?? null,
            'icon_bg_color' => $validated['icon_bg_color'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'shared_group_id' => $sharedGroupId,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'folder' => new ChatFolderResource($folder),
        ], 201);
    }

    public function update(Request $request, ChatFolder $folder): JsonResponse
    {
        abort_unless(
            (int) $folder->user_id === (int) $request->user()->id
            || ($folder->shared_group_id !== null && Group::query()
                ->visibleToUser((int) $request->user()->id)
                ->whereKey((int) $folder->shared_group_id)
                ->exists()),
            404
        );
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'icon_emoji' => ['sometimes', 'nullable', 'string', 'max:16'],
            'icon_bg_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'parent_id' => ['sometimes', 'nullable', Rule::notIn([$folder->id]), 'exists:chat_folders,id'],
            'shared_group_id' => ['sometimes', 'nullable', 'exists:groups,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        if (array_key_exists('shared_group_id', $validated) && $validated['shared_group_id'] !== null) {
            $allowed = Group::query()
                ->visibleToUser((int) $request->user()->id)
                ->whereKey((int) $validated['shared_group_id'])
                ->exists();
            abort_unless($allowed, 422, 'Invalid shared group.');
        }
        $folder->fill($validated);
        $folder->save();

        return response()->json([
            'folder' => new ChatFolderResource($folder),
        ]);
    }

    public function destroy(Request $request, ChatFolder $folder): JsonResponse
    {
        abort_unless((int) $folder->user_id === (int) $request->user()->id, 404);
        Chat::query()->where('folder_id', $folder->id)->update(['folder_id' => null]);
        $folder->delete();

        return response()->json([
            'ok' => true,
        ]);
    }
}
