<?php

namespace App\Support;

use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\User;

class CommunityGuestAccess
{
    public static function ensureVisitorMembership(User $user, Community $community): CommunityMembership
    {
        $existing = CommunityMembership::query()
            ->where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing instanceof CommunityMembership) {
            return $existing;
        }

        return CommunityMembership::query()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => 'visitor',
        ]);
    }

    public static function hasMembership(User $user, Community $community): bool
    {
        return CommunityMembership::query()
            ->where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
