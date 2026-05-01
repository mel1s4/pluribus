<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyCommunitiesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $rows = $user->communities()
            ->orderBy('communities.name')
            ->get(['communities.id', 'communities.name', 'communities.slug'])
            ->map(fn (Community $community): array => [
                'id' => $community->id,
                'name' => $community->name,
                'slug' => $community->slug,
                'role' => (string) ($community->pivot?->role ?? 'member'),
            ])
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function joinByInvitation(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $invitation = \App\Models\CommunityInvitation::findByPlainToken($token);
        if ($invitation === null || ! $invitation->isUsable()) {
            abort(422, 'Invitation is not valid.');
        }

        CommunityMembership::query()->updateOrCreate(
            [
                'community_id' => $invitation->community_id,
                'user_id' => $user->id,
            ],
            ['role' => 'member']
        );
        $invitation->increment('uses_count');

        return response()->json(['ok' => true]);
    }
}
