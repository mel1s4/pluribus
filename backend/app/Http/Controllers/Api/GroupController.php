<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Community;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $groups = Group::query()
            ->visibleToUser((int) $request->user()->id)
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return GroupResource::collection($groups);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $group = DB::transaction(function () use ($request): Group {
            $group = Group::query()->create([
                'community_id' => Community::current()->id,
                'owner_id' => $request->user()->id,
                'name' => (string) $request->validated('name'),
                'description' => $request->validated('description'),
            ]);

            $memberIds = collect($request->validated('member_ids', []))
                ->map(fn ($id) => (int) $id)
                ->push((int) $request->user()->id)
                ->unique()
                ->values();

            $sync = [];
            foreach ($memberIds as $id) {
                $sync[$id] = [
                    'role' => $id === (int) $request->user()->id ? Group::ROLE_OWNER : Group::ROLE_MEMBER,
                    'joined_at' => now(),
                ];
            }
            $group->members()->sync($sync);

            return $group->loadCount('members');
        });

        return response()->json(['group' => new GroupResource($group)], 201);
    }

    public function show(Request $request, Group $group): JsonResponse
    {
        $this->authorize('view', $group);

        $group->loadCount('members');

        return response()->json(['group' => new GroupResource($group)]);
    }

    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        $this->authorize('update', $group);

        $validated = $request->validated();

        DB::transaction(function () use ($group, $validated): void {
            $group->fill(collect($validated)->only(['name', 'description'])->toArray());
            if (array_key_exists('owner_id', $validated) && $validated['owner_id']) {
                $newOwnerId = (int) $validated['owner_id'];
                $group->owner_id = $newOwnerId;
                $group->members()->updateExistingPivot($newOwnerId, ['role' => Group::ROLE_OWNER]);
            }
            $group->save();
        });

        $group->loadCount('members');

        return response()->json(['group' => new GroupResource($group)]);
    }

    public function destroy(Request $request, Group $group): JsonResponse
    {
        $this->authorize('delete', $group);
        $group->delete();

        return response()->json(['ok' => true]);
    }
}

