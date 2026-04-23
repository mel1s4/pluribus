<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceAudienceRequest;
use App\Http\Requests\UpdatePlaceAudienceRequest;
use App\Http\Resources\CommunityMemberPickerResource;
use App\Http\Resources\PlaceAudienceResource;
use App\Models\Place;
use App\Models\PlaceAudience;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlaceAudienceController extends Controller
{
    public function pickableMembers(Request $request, Place $place): AnonymousResourceCollection
    {
        $this->authorize('view', $place);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'avatar_path']);

        return CommunityMemberPickerResource::collection($users);
    }

    public function index(Request $request, Place $place): AnonymousResourceCollection
    {
        $this->authorize('view', $place);

        $audiences = $place->audiences()
            ->with('members')
            ->orderBy('id')
            ->get();

        return PlaceAudienceResource::collection($audiences);
    }

    public function store(StorePlaceAudienceRequest $request, Place $place): JsonResponse
    {
        $this->authorize('update', $place);

        $validated = $request->validated();
        $userIds = array_values(array_unique(array_map('intval', $validated['user_ids'] ?? [])));

        $audience = $place->audiences()->create([
            'name' => $validated['name'],
        ]);
        $audience->members()->sync($userIds);

        return response()->json([
            'audience' => new PlaceAudienceResource($audience->load('members')),
        ], 201);
    }

    public function update(UpdatePlaceAudienceRequest $request, Place $place, PlaceAudience $audience): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $audience->place_id !== (int) $place->id) {
            abort(404);
        }

        $validated = $request->validated();
        if (array_key_exists('name', $validated)) {
            $audience->name = $validated['name'];
            $audience->save();
        }
        if (array_key_exists('user_ids', $validated)) {
            $userIds = array_values(array_unique(array_map('intval', $validated['user_ids'] ?? [])));
            $audience->members()->sync($userIds);
        }

        return response()->json([
            'audience' => new PlaceAudienceResource($audience->fresh()->load('members')),
        ]);
    }

    public function destroy(Request $request, Place $place, PlaceAudience $audience): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $audience->place_id !== (int) $place->id) {
            abort(404);
        }

        $audience->delete();

        return response()->json(['ok' => true]);
    }
}
