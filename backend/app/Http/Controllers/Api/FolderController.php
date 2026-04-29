<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
use App\Http\Resources\FolderResource;
use App\Http\Resources\TaskResource;
use App\Models\Chat;
use App\Models\Folder;
use App\Models\Group;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FolderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $folders = Folder::query()
            ->visibleToUser((int) $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return FolderResource::collection($folders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon_emoji' => ['nullable', 'string', 'max:16'],
            'icon_bg_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'parent_id' => ['nullable', 'exists:folders,id'],
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

        $folder = Folder::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'icon_emoji' => $validated['icon_emoji'] ?? null,
            'icon_bg_color' => $validated['icon_bg_color'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'shared_group_id' => $sharedGroupId,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'folder' => new FolderResource($folder),
        ], 201);
    }

    public function update(Request $request, Folder $folder): JsonResponse
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
            'parent_id' => ['sometimes', 'nullable', Rule::notIn([$folder->id]), 'exists:folders,id'],
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
            'folder' => new FolderResource($folder),
        ]);
    }

    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        abort_unless((int) $folder->user_id === (int) $request->user()->id, 404);
        Chat::query()->where('folder_id', $folder->id)->update(['folder_id' => null]);
        $folder->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function stats(Request $request, Folder $folder): JsonResponse
    {
        $this->authorizeFolderAccess($request, $folder);

        $chatsCount = Chat::query()
            ->visibleToUser((int) $request->user()->id)
            ->where('folder_id', $folder->id)
            ->count();

        $tasksCount = Task::query()
            ->visibleToUser((int) $request->user()->id)
            ->where('folder_id', $folder->id)
            ->count();

        $childrenCount = Folder::query()
            ->visibleToUser((int) $request->user()->id)
            ->where('parent_id', $folder->id)
            ->count();

        $lastChatAt = Chat::query()
            ->visibleToUser((int) $request->user()->id)
            ->where('folder_id', $folder->id)
            ->max('updated_at');

        $lastTaskAt = Task::query()
            ->visibleToUser((int) $request->user()->id)
            ->where('folder_id', $folder->id)
            ->max('updated_at');

        $lastActivity = collect([$lastChatAt, $lastTaskAt, $folder->updated_at])
            ->filter()
            ->max();

        return response()->json([
            'stats' => [
                'chats_count' => $chatsCount,
                'tasks_count' => $tasksCount,
                'children_count' => $childrenCount,
                'last_activity_at' => $lastActivity?->toIso8601String(),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:200'],
            'type' => ['sometimes', 'nullable', Rule::in(['all', 'folder', 'chat', 'task'])],
        ]);

        $userId = (int) $request->user()->id;
        $type = (string) ($validated['type'] ?? 'all');
        $term = '%'.addcslashes($validated['q'], '%_\\').'%';

        $folders = collect();
        if ($type === 'all' || $type === 'folder') {
            $folders = Folder::query()
                ->visibleToUser($userId)
                ->where('name', 'like', $term)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(50)
                ->get();
        }

        $chats = collect();
        if ($type === 'all' || $type === 'chat') {
            $chats = Chat::query()
                ->visibleToUser($userId)
                ->with(['folder:id,name,icon_emoji,icon_bg_color,parent_id'])
                ->where(function ($q) use ($term): void {
                    $q->where('title', 'like', $term);
                })
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get();
        }

        $tasks = collect();
        if ($type === 'all' || $type === 'task') {
            $tasks = Task::query()
                ->visibleToUser($userId)
                ->with(['folder:id,name,icon_emoji,icon_bg_color,parent_id'])
                ->where(function ($q) use ($term): void {
                    $q->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('content_markdown', 'like', $term);
                })
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get();
        }

        return response()->json([
            'folders' => FolderResource::collection($folders),
            'chats' => ChatResource::collection($chats),
            'tasks' => TaskResource::collection($tasks),
        ]);
    }

    public function bulkMove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.type' => ['required', Rule::in(['chat', 'task'])],
            'items.*.id' => ['required', 'integer'],
        ]);

        $userId = (int) $request->user()->id;
        $targetFolderId = $validated['target_folder_id'] ?? null;

        if ($targetFolderId !== null) {
            Folder::query()
                ->visibleToUser($userId)
                ->whereKey($targetFolderId)
                ->firstOrFail();
        }

        DB::transaction(function () use ($validated, $userId, $targetFolderId, $request): void {
            foreach ($validated['items'] as $item) {
                if ($item['type'] === 'chat') {
                    $chat = Chat::query()
                        ->visibleToUser($userId)
                        ->whereKey((int) $item['id'])
                        ->firstOrFail();
                    Gate::forUser($request->user())->authorize('update', $chat);
                    $chat->update(['folder_id' => $targetFolderId]);
                } else {
                    $task = Task::query()
                        ->visibleToUser($userId)
                        ->whereKey((int) $item['id'])
                        ->firstOrFail();
                    Gate::forUser($request->user())->authorize('update', $task);
                    $task->update(['folder_id' => $targetFolderId]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folders' => ['required', 'array', 'min:1'],
            'folders.*.id' => ['required', 'integer', 'exists:folders,id'],
            'folders.*.sort_order' => ['required', 'integer', 'min:0'],
            'folders.*.parent_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        $userId = (int) $request->user()->id;
        $rows = $validated['folders'];

        $ids = collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $foldersById = Folder::query()
            ->visibleToUser($userId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        abort_unless(count($foldersById) === count($ids), 422, 'Invalid folder selection.');

        foreach ($foldersById as $folder) {
            abort_unless(
                (int) $folder->user_id === $userId
                    || ($folder->shared_group_id !== null && Group::query()
                        ->visibleToUser($userId)
                        ->whereKey((int) $folder->shared_group_id)
                        ->exists()),
                403
            );
        }

        $newParents = [];
        foreach ($rows as $row) {
            $fid = (int) $row['id'];
            if (array_key_exists('parent_id', $row)) {
                $newParents[$fid] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            } else {
                $newParents[$fid] = $foldersById[$fid]->parent_id !== null ? (int) $foldersById[$fid]->parent_id : null;
            }
        }

        foreach ($rows as $row) {
            $folderId = (int) $row['id'];
            $parentId = $newParents[$folderId];

            if ($parentId !== null) {
                abort_unless(
                    Folder::query()->visibleToUser($userId)->whereKey($parentId)->exists(),
                    422,
                    'Invalid parent folder.'
                );
                abort_if((int) $parentId === $folderId, 422, 'Folder cannot be its own parent.');
                abort_if($this->wouldCreateFolderCycle($folderId, $parentId), 422, 'Invalid folder hierarchy.');
            }
        }

        DB::transaction(function () use ($rows, $foldersById): void {
            foreach ($rows as $row) {
                $folder = $foldersById[(int) $row['id']];
                $updates = ['sort_order' => (int) $row['sort_order']];
                if (array_key_exists('parent_id', $row)) {
                    $updates['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
                }
                $folder->fill($updates);
                $folder->save();
            }
        });

        return response()->json(['ok' => true]);
    }

    private function authorizeFolderAccess(Request $request, Folder $folder): void
    {
        abort_unless(
            Folder::query()
                ->visibleToUser((int) $request->user()->id)
                ->whereKey($folder->id)
                ->exists(),
            404
        );
    }

    /**
     * True if assigning $potentialParentId as parent of $folderId would create a cycle.
     */
    private function wouldCreateFolderCycle(int $folderId, int $potentialParentId): bool
    {
        $cursor = $potentialParentId;
        $guard = 0;
        while ($cursor !== null && $guard++ < 1000) {
            if ((int) $cursor === $folderId) {
                return true;
            }
            $cursor = Folder::query()->whereKey($cursor)->value('parent_id');
        }

        return false;
    }
}
