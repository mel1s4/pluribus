<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCalendarRequest;
use App\Http\Requests\UpdateCalendarRequest;
use App\Http\Resources\CalendarResource;
use App\Models\Calendar;
use App\Models\Community;
use App\Models\Post;
use App\Models\Task;
use App\Services\CalendarEventsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CalendarController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $calendars = Calendar::query()
            ->visibleToUser((int) $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return CalendarResource::collection($calendars);
    }

    public function store(StoreCalendarRequest $request): JsonResponse
    {
        $calendar = Calendar::query()->create([
            'community_id' => Community::current()->id,
            'owner_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json(['calendar' => new CalendarResource($calendar)], 201);
    }

    public function events(Request $request, Calendar $calendar): JsonResponse
    {
        $this->authorize('view', $calendar);

        $userId = (int) $request->user()->id;
        $start = $request->query('start');
        $end = $request->query('end');

        $posts = Post::query()
            ->visibleToUser($userId)
            ->where('calendar_id', $calendar->id)
            ->whereIn('type', [Post::TYPE_EVENT, Post::TYPE_ANNOUNCEMENT, Post::TYPE_INFO])
            ->when($start, fn ($q) => $q->where(function ($q2) use ($start): void {
                $q2->whereNull('end_at')->orWhere('end_at', '>=', $start);
            }))
            ->when($end, fn ($q) => $q->where(function ($q2) use ($end): void {
                $q2->whereNull('start_at')->orWhere('start_at', '<=', $end);
            }))
            ->orderBy('start_at')
            ->get();

        $tasks = Task::query()
            ->visibleToUser($userId)
            ->where('calendar_id', $calendar->id)
            ->when($start, fn ($q) => $q->where(function ($q2) use ($start): void {
                $q2->whereNull('end_at')->orWhere('end_at', '>=', $start);
            }))
            ->when($end, fn ($q) => $q->where(function ($q2) use ($end): void {
                $q2->whereNull('start_at')->orWhere('start_at', '<=', $end);
            }))
            ->orderBy('start_at')
            ->get();

        return response()->json([
            'events' => CalendarEventsPresenter::mergedEvents($request, $posts, $tasks),
        ]);
    }

    public function show(Request $request, Calendar $calendar): JsonResponse
    {
        $this->authorize('view', $calendar);

        return response()->json(['calendar' => new CalendarResource($calendar)]);
    }

    public function update(UpdateCalendarRequest $request, Calendar $calendar): JsonResponse
    {
        $this->authorize('update', $calendar);
        $calendar->fill($request->validated());
        $calendar->save();

        return response()->json(['calendar' => new CalendarResource($calendar)]);
    }

    public function destroy(Request $request, Calendar $calendar): JsonResponse
    {
        $this->authorize('delete', $calendar);
        $calendar->delete();

        return response()->json(['ok' => true]);
    }
}

