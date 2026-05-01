<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Http\Resources\PostResource;
use App\Models\Community;
use App\Models\Place;
use App\Models\PlaceOffer;
use App\Models\Post;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Support\WalletMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommunityMicrositeController extends Controller
{
    public function show(Request $request, string $slug): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $user = $request->user();
        $isMember = $user instanceof User
            && $user->communities()->where('communities.id', $community->id)->exists();

        $grantRaw = (string) (WalletLedgerEntry::query()
            ->where('community_id', $community->id)
            ->where('type', WalletLedgerEntry::TYPE_GRANT)
            ->where('actor_kind', WalletLedgerEntry::ACTOR_COMMUNITY_GRANT)
            ->sum('amount') ?? '0');
        $grantTotal = WalletMoney::normalize($grantRaw);

        $stewards = $this->stewardsForCommunity($community->id);

        $publicPlaces = Place::query()
            ->where('is_public', true)
            ->whereHas('offers', function ($q): void {
                $q->where('visibility_scope', PlaceOffer::VISIBILITY_SCOPE_PUBLIC);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $payload = [
            'community' => (new CommunityResource($community))->toArray($request),
            'is_member' => $isMember,
            'credits_granted_total' => $grantTotal,
            'stewards' => $stewards,
            'public_places' => $publicPlaces->map(fn (Place $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
            ])->values()->all(),
        ];

        if ($isMember && $user instanceof User) {
            $uid = (int) $user->id;
            $memberPlaces = Place::query()
                ->whereHas('offers', fn ($q) => $q->visibleToUser($uid))
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);

            $recentPosts = Post::query()
                ->visibleToUser($uid)
                ->where('community_id', $community->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get();

            $payload['member_places'] = $memberPlaces->map(fn (Place $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
            ])->values()->all();
            $payload['recent_posts'] = PostResource::collection($recentPosts)->resolve();
        }

        return response()->json($payload);
    }

    /**
     * Community-facing stewards: pivot admins and developers.
     *
     * @return list<array<string, mixed>>
     */
    private function stewardsForCommunity(int $communityId): array
    {
        $rows = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.username',
                'users.avatar_path',
                'community_user.role as steward_role',
            ])
            ->join('community_user', 'community_user.user_id', '=', 'users.id')
            ->where('community_user.community_id', $communityId)
            ->whereIn('community_user.role', ['admin', 'developer'])
            ->orderBy('users.name')
            ->get();

        return $rows->map(fn (User $u): array => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'membership_role' => (string) ($u->steward_role ?? ''),
            'avatar_url' => $u->avatar_path
                ? Storage::disk('public')->url($u->avatar_path)
                : null,
        ])->values()->all();
    }
}
