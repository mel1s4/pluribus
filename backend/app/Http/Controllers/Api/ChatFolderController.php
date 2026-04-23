<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatFolderResource;
use App\Models\Chat;
use App\Models\ChatFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ChatFolderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $folders = ChatFolder::query()
            ->forUser((int) $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ChatFolderResource::collection($folders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:chat_folders,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $folder = ChatFolder::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'folder' => new ChatFolderResource($folder),
        ], 201);
    }

    public function update(Request $request, ChatFolder $folder): JsonResponse
    {
        abort_unless((int) $folder->user_id === (int) $request->user()->id, 404);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', Rule::notIn([$folder->id]), 'exists:chat_folders,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
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
