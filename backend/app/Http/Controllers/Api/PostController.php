<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Community;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Post::query()
            ->visibleToUser((int) $request->user()->id)
            ->orderByDesc('start_at')
            ->orderByDesc('id');

        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }
        if ($request->filled('calendar_id')) {
            $query->where('calendar_id', (int) $request->query('calendar_id'));
        }

        $posts = $query->get();

        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::query()->create([
            'community_id' => Community::current()->id,
            'author_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json(['post' => new PostResource($post)], 201);
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        return response()->json(['post' => new PostResource($post)]);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);
        $post->fill($request->validated());
        $post->save();

        return response()->json(['post' => new PostResource($post)]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        $post->delete();

        return response()->json(['ok' => true]);
    }
}

