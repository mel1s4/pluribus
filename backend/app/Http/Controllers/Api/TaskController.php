<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Community;
use App\Models\Post;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Task::query()
            ->visibleToUser((int) $request->user()->id)
            ->with(['post', 'folder'])
            ->orderBy('position')
            ->orderByDesc('id');

        if ($request->filled('folder_id')) {
            $query->where('folder_id', (int) $request->query('folder_id'));
        }
        if ($request->boolean('only_open')) {
            $query->whereNull('completed_at');
        }

        return TaskResource::collection($query->get());
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = DB::transaction(function () use ($request): Task {
            $validated = $request->validated();
            $post = Post::query()->create([
                'community_id' => Community::current()->id,
                'author_id' => $request->user()->id,
                'type' => Post::TYPE_TASK,
                'title' => (string) $validated['title'],
                'description' => $validated['description'] ?? null,
                'content_markdown' => $validated['content_markdown'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'shared_group_id' => $validated['shared_group_id'] ?? null,
                'calendar_id' => $validated['calendar_id'] ?? null,
                'place_id' => $validated['place_id'] ?? null,
                'start_at' => $validated['start_at'] ?? null,
                'end_at' => $validated['end_at'] ?? null,
                'all_day' => (bool) ($validated['all_day'] ?? false),
                'recurrence_rule' => $validated['recurrence_rule'] ?? null,
                'recurrence_id' => $validated['recurrence_id'] ?? null,
                'visibility_scope' => (string) $validated['visibility_scope'],
            ]);

            return Task::query()->create([
                'post_id' => $post->id,
                'folder_id' => $validated['folder_id'] ?? null,
                'assignee_id' => $validated['assignee_id'] ?? null,
                'position' => (int) ($validated['position'] ?? 0),
                'completed_at' => $validated['completed_at'] ?? null,
                'highlighted' => (bool) ($validated['highlighted'] ?? false),
            ]);
        });

        return response()->json(['task' => new TaskResource($task->load('post'))], 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json(['task' => new TaskResource($task->load(['post', 'folder']))]);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        DB::transaction(function () use ($request, $task): void {
            $validated = $request->validated();

            $task->fill(collect($validated)->only([
                'folder_id',
                'assignee_id',
                'position',
                'completed_at',
                'highlighted',
            ])->toArray());
            $task->save();

            $task->post->fill(collect($validated)->only([
                'shared_group_id',
                'calendar_id',
                'place_id',
                'title',
                'description',
                'content_markdown',
                'tags',
                'start_at',
                'end_at',
                'all_day',
                'recurrence_rule',
                'recurrence_id',
                'visibility_scope',
            ])->toArray());
            $task->post->save();
        });

        return response()->json(['task' => new TaskResource($task->load('post'))]);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $task->delete();

        return response()->json(['ok' => true]);
    }
}

