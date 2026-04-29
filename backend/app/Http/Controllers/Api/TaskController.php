<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Community;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Task::query()
            ->visibleToUser((int) $request->user()->id)
            ->with(['folder', 'assignee'])
            ->orderBy('position')
            ->orderByDesc('id');

        if ($request->filled('folder_id')) {
            $query->where('folder_id', (int) $request->query('folder_id'));
        }
        if ($request->filled('calendar_id')) {
            $query->where('calendar_id', (int) $request->query('calendar_id'));
        }
        if ($request->boolean('only_open')) {
            $query->whereNull('completed_at');
        }

        return TaskResource::collection($query->get());
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $task = Task::query()->create([
            ...$validated,
            'community_id' => Community::current()->id,
            'author_id' => $request->user()->id,
            'visibility_scope' => (string) ($validated['visibility_scope'] ?? Task::VISIBILITY_PRIVATE),
        ]);

        return response()->json(['task' => new TaskResource($task->load(['folder', 'assignee']))], 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json(['task' => new TaskResource($task->load(['folder', 'assignee']))]);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task->fill($request->validated());
        $task->save();

        return response()->json(['task' => new TaskResource($task->load(['folder', 'assignee']))]);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $task->delete();

        return response()->json(['ok' => true]);
    }
}

