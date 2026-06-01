<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaceResource;
use App\Http\Resources\PostResource;
use App\Models\Calendar;
use App\Models\Community;
use App\Models\Place;
use App\Models\Post;
use App\Models\Task;
use App\Models\User;
use App\Services\CalendarEventsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    public function calendar(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $start = $request->query('start');
        $end = $request->query('end');

        $calendarIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->query('calendar_ids', [])),
            fn (int $id) => $id > 0
        )));

        $posts = Post::query()
            ->visibleToUser($userId)
            ->whereIn('type', [Post::TYPE_EVENT, Post::TYPE_ANNOUNCEMENT, Post::TYPE_INFO])
            ->when(count($calendarIds) > 0, fn ($q) => $q->whereIn('calendar_id', $calendarIds))
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
            ->whereNotNull('calendar_id')
            ->when(count($calendarIds) > 0, fn ($q) => $q->whereIn('calendar_id', $calendarIds))
            ->when($start, fn ($q) => $q->where(function ($q2) use ($start): void {
                $q2->whereNull('end_at')->orWhere('end_at', '>=', $start);
            }))
            ->when($end, fn ($q) => $q->where(function ($q2) use ($end): void {
                $q2->whereNull('start_at')->orWhere('start_at', '<=', $end);
            }))
            ->orderBy('start_at')
            ->get();

        $calendars = Calendar::query()
            ->visibleToUser($userId)
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'visibility_scope', 'shared_group_id']);

        return response()->json([
            'events' => CalendarEventsPresenter::mergedEvents($request, $posts, $tasks),
            'calendars' => $calendars,
        ]);
    }

    public function map(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?: $request->user();
        $entity = (string) $request->query('entity', 'both');
        $tags = array_values(array_filter((array) $request->query('tags', []), fn ($t) => is_string($t) && $t !== ''));
        $postType = $request->query('post_type');
        $communityId = $this->resolveMapCommunityId($request, $user);

        if ($user === null) {
            $placesPublicOnly = true;
        } else {
            $placesScope = (string) $request->query('places_scope', 'all');
            $placesPublicOnly = $placesScope === 'public';
        }

        $places = collect();
        if (in_array($entity, ['both', 'places'], true)) {
            $places = Place::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->when($placesPublicOnly, fn ($q) => $q->where('is_public', true))
                ->with('administrators')
                ->when(count($tags) > 0, function ($q) use ($tags): void {
                    foreach ($tags as $tag) {
                        $q->whereJsonContains('tags', $tag);
                    }
                })
                ->orderBy('name')
                ->get();
        }

        $posts = collect();
        if (in_array($entity, ['both', 'posts'], true)) {
            $postsQuery = Post::query()
                ->where('community_id', $communityId)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->when($postType, fn ($q) => $q->where('type', (string) $postType))
                ->when(count($tags) > 0, function ($q) use ($tags): void {
                    foreach ($tags as $tag) {
                        $q->whereJsonContains('tags', $tag);
                    }
                });

            if ($user instanceof User) {
                $postsQuery->visibleToUser((int) $user->id);
            } else {
                $postsQuery->where('visibility_scope', Post::VISIBILITY_COMMUNITY);
            }

            $posts = $postsQuery->orderByDesc('created_at')->get();
        }

        return response()->json([
            'places' => PlaceResource::collection($places),
            'posts' => PostResource::collection($posts),
        ]);
    }

    private function resolveMapCommunityId(Request $request, ?User $user): int
    {
        if ($user instanceof User) {
            $requested = (int) $request->query('community_id', 0);
            if ($requested > 0) {
                return $requested;
            }
        }
        $active = $request->attributes->get('active_community');
        if ($active instanceof Community) {
            return (int) $active->id;
        }

        return Community::forRequest($request)->id;
    }
}
