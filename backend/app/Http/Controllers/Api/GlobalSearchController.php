<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Search\SearchMemberResource;
use App\Http\Resources\Search\SearchOfferResource;
use App\Http\Resources\Search\SearchPlaceResource;
use App\Http\Resources\Search\SearchRequirementResource;
use App\Models\Place;
use App\Models\PlaceOffer;
use App\Models\PlaceRequirement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $uid = (int) $request->user()->id;
        $limit = min(20, max(1, (int) $request->query('limit', 6)));

        if ($q === '') {
            return response()->json([
                'members' => [],
                'offers' => [],
                'requirements' => [],
                'places' => [],
            ]);
        }

        $like = '%'.$q.'%';

        $members = User::query()
            ->where(function ($query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $offers = PlaceOffer::query()
            ->with(['place:id,name,slug'])
            ->visibleToUser($uid)
            ->where(function ($query) use ($like, $q): void {
                $query->where('title', 'like', $like)
                    ->orWhereJsonContains('tags', $q)
                    ->orWhereHas('place', fn ($placeQuery) => $placeQuery->where('name', 'like', $like));
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $requirements = PlaceRequirement::query()
            ->with(['place:id,name,slug'])
            ->visibleToUser($uid)
            ->where(function ($query) use ($like, $q): void {
                $query->where('title', 'like', $like)
                    ->orWhereJsonContains('tags', $q)
                    ->orWhereHas('place', fn ($placeQuery) => $placeQuery->where('name', 'like', $like));
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $places = Place::query()
            ->where(function ($query) use ($like, $q): void {
                $query->where('name', 'like', $like)
                    ->orWhereJsonContains('tags', $q);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'members' => SearchMemberResource::collection($members)->resolve(),
            'offers' => SearchOfferResource::collection($offers)->resolve(),
            'requirements' => SearchRequirementResource::collection($requirements)->resolve(),
            'places' => SearchPlaceResource::collection($places)->resolve(),
        ]);
    }
}
