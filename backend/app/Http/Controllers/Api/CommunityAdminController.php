<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommunityRequest;
use App\Http\Requests\UpdateCommunityRequest;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommunityAdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('communities.view');

        $communities = Community::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return CommunityResource::collection($communities);
    }

    public function show(Request $request, Community $community): JsonResponse
    {
        $this->authorize('communities.view');

        return response()->json([
            'data' => (new CommunityResource($community))->resolve(),
        ]);
    }

    public function store(StoreCommunityRequest $request): JsonResponse
    {
        $this->authorize('communities.manage');

        $community = Community::query()->create($request->validated());

        return response()->json([
            'community' => new CommunityResource($community),
        ], 201);
    }

    public function update(UpdateCommunityRequest $request, Community $community): JsonResponse
    {
        $this->authorize('communities.manage');

        $community->fill($request->validated());
        $community->save();

        return response()->json([
            'community' => new CommunityResource($community->fresh()),
        ]);
    }
}
