<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupMemberResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class GroupMemberController extends Controller
{
    public function index(Request $request, Group $group): AnonymousResourceCollection
    {
        $this->authorize('view', $group);

        $members = $group->members()
            ->orderBy('name')
            ->get();

        return GroupMemberResource::collection($members);
    }

    public function store(Request $request, Group $group): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', Rule::in(Group::ROLES)],
        ]);

        $userId = (int) $validated['user_id'];
        $role = (string) ($validated['role'] ?? Group::ROLE_MEMBER);
        $group->members()->syncWithoutDetaching([
            $userId => ['role' => $role, 'joined_at' => now()],
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function update(Request $request, Group $group, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        $validated = $request->validate([
            'role' => ['required', Rule::in(Group::ROLES)],
        ]);

        $group->members()->updateExistingPivot((int) $user->id, ['role' => $validated['role']]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Group $group, User $user): JsonResponse
    {
        $actor = $request->user();
        $isSelf = (int) $user->id === (int) $actor->id;

        if (! $isSelf) {
            $this->authorize('manageMembers', $group);
        } else {
            $this->authorize('view', $group);
        }

        if ((int) $group->owner_id === (int) $user->id) {
            return response()->json(['message' => 'Transfer ownership before removing the owner.'], 422);
        }

        $group->members()->detach((int) $user->id);

        return response()->json(['ok' => true]);
    }
}

