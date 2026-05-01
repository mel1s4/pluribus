<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityMembershipResource;
use App\Models\Community;
use App\Models\User;
use App\Support\CommunityPivotAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommunityMembershipController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('community.memberships.manage');
        $community = $this->requireActiveCommunity($request);
        CommunityPivotAdmin::assertRootOrPivotAdmin($request->user(), $community);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', 'in:admin,member'],
        ]);

        $userId = (int) $validated['user_id'];
        $role = $validated['role'];

        $user = User::query()->findOrFail($userId);
        if ($user->communities()->where('communities.id', $community->id)->exists()) {
            return response()->json(['message' => 'User is already a member of this community.'], 422);
        }

        $user->communities()->attach($community->id, ['role' => $role]);

        $fresh = $community->members()
            ->where('users.id', $userId)
            ->withPivot(['role'])
            ->first();
        if ($fresh === null) {
            abort(500);
        }

        return response()->json([
            'member' => (new CommunityMembershipResource($fresh))->resolve(),
        ], 201);
    }

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('community.memberships.manage');
        $community = $this->requireActiveCommunity($request);
        CommunityPivotAdmin::assertRootOrPivotAdmin($request->user(), $community);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $paginator = $community->members()
            ->withPivot(['role'])
            ->orderBy('users.name')
            ->paginate($perPage);

        return CommunityMembershipResource::collection($paginator)
            ->additional([
                'community_id' => (int) $community->id,
            ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('community.memberships.manage');
        $community = $this->requireActiveCommunity($request);
        CommunityPivotAdmin::assertRootOrPivotAdmin($request->user(), $community);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,member'],
        ]);
        $newRole = $validated['role'];

        $membership = $user->communities()
            ->where('communities.id', $community->id)
            ->first();
        if ($membership === null) {
            return response()->json(['message' => 'User is not a member of this community.'], 404);
        }

        $currentPivot = $membership->pivot;
        $currentRole = $currentPivot !== null ? (string) $currentPivot->role : '';

        if (! in_array($currentRole, ['admin', 'member'], true)) {
            return response()->json(['message' => 'Membership role can only be changed for admin or member roles.'], 422);
        }

        $actor = $request->user();
        if (! ($actor instanceof User)) {
            abort(401);
        }

        if ($currentRole === 'admin' && $newRole === 'member') {
            $adminCount = $community->members()->wherePivot('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'Cannot demote the last community administrator.'], 422);
            }
        }

        if ($currentRole === 'admin' && $newRole === 'member' && (int) $user->id === (int) $actor->id) {
            $otherAdmins = $community->members()
                ->wherePivot('role', 'admin')
                ->where('users.id', '!=', $actor->id)
                ->count();
            if ($otherAdmins < 1) {
                return response()->json(['message' => 'Assign another administrator before stepping down.'], 422);
            }
        }

        $community->members()->updateExistingPivot($user->id, ['role' => $newRole]);

        $fresh = $community->members()
            ->where('users.id', $user->id)
            ->withPivot(['role'])
            ->first();
        if ($fresh === null) {
            abort(500);
        }

        return response()->json([
            'member' => (new CommunityMembershipResource($fresh))->resolve(),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('community.memberships.manage');
        $community = $this->requireActiveCommunity($request);
        CommunityPivotAdmin::assertRootOrPivotAdmin($request->user(), $community);

        $membership = $user->communities()
            ->where('communities.id', $community->id)
            ->first();
        if ($membership === null) {
            return response()->json(['message' => 'User is not a member of this community.'], 404);
        }

        $currentRole = (string) ($membership->pivot?->role ?? '');
        if ($currentRole === 'admin') {
            $adminCount = $community->members()->wherePivot('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'Cannot remove the last community administrator.'], 422);
            }
        }

        $community->members()->detach($user->id);

        return response()->json(null, 204);
    }

    private function requireActiveCommunity(Request $request): Community
    {
        $active = $request->attributes->get('active_community');
        if (! $active instanceof Community) {
            abort(403, 'Missing or unknown community scope (send X-Community-Slug header).');
        }

        return $active;
    }
}
