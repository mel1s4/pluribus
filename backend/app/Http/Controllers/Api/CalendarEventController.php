<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RescheduleCalendarEventRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\TaskResource;
use App\Models\Post;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class CalendarEventController extends Controller
{
    public function reschedule(RescheduleCalendarEventRequest $request, string $type, int $id): JsonResponse
    {
        $type = strtolower($type);
        $validated = $request->validated();

        if ($type === 'post') {
            $post = Post::query()->findOrFail($id);
            $this->authorize('update', $post);
            $post->fill([
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'] ?? null,
                'all_day' => (bool) ($validated['all_day'] ?? $post->all_day),
            ]);
            $post->save();

            return response()->json([
                'event' => array_merge(
                    (new PostResource($post))->toArray($request),
                    ['entity_type' => 'post']
                ),
            ]);
        }

        if ($type === 'task') {
            $task = Task::query()->findOrFail($id);
            $this->authorize('update', $task);
            $task->fill([
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'] ?? null,
                'all_day' => (bool) ($validated['all_day'] ?? $task->all_day),
            ]);
            $task->save();

            return response()->json([
                'event' => array_merge(
                    (new TaskResource($task))->toArray($request),
                    ['entity_type' => 'task']
                ),
            ]);
        }

        abort(404);
    }
}
