<?php

namespace App\Support;

use App\Models\Community;
use App\Models\User;

final class CommunityPivotAdmin
{
    /**
     * Root may manage any community. Otherwise the actor must have pivot role "admin" on this community.
     */
    public static function isRootOrPivotAdmin(?User $actor, Community $community): bool
    {
        if (! $actor instanceof User) {
            return false;
        }
        if ($actor->isRoot()) {
            return true;
        }

        $role = $actor->communities()
            ->where('communities.id', $community->id)
            ->value('community_user.role');

        return $role === 'admin';
    }

    public static function assertRootOrPivotAdmin(?User $actor, Community $community): void
    {
        if (! self::isRootOrPivotAdmin($actor, $community)) {
            abort(403, 'Only community administrators may perform this action.');
        }
    }
}
