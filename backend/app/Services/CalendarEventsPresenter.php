<?php

namespace App\Services;

use App\Http\Resources\PostResource;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CalendarEventsPresenter
{
    /**
     * @param  Collection<int, \App\Models\Post>  $posts
     * @param  Collection<int, \App\Models\Task>  $tasks
     * @return list<array<string, mixed>>
     */
    public static function mergedEvents(Request $request, Collection $posts, Collection $tasks): array
    {
        $postRows = $posts->map(fn ($post) => array_merge(
            (new PostResource($post))->toArray($request),
            ['entity_type' => 'post']
        ));

        $taskRows = $tasks->map(fn ($task) => array_merge(
            (new TaskResource($task))->toArray($request),
            ['entity_type' => 'task']
        ));

        return $postRows
            ->concat($taskRows)
            ->sortBy(fn (array $e) => $e['start_at'] ?? '')
            ->values()
            ->all();
    }
}
