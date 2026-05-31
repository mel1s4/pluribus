<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\CommunityProject;
use App\Models\User;
use App\Support\CommunityPivotAdmin;

class CommunityProjectPolicy
{
    public function viewAny(User $user, Community $community): bool
    {
        return $this->isMember($user, $community);
    }

    public function view(User $user, CommunityProject $project): bool
    {
        return $this->isMember($user, $project->community);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->isMember($user, $community);
    }

    public function update(User $user, CommunityProject $project): bool
    {
        if (! $this->isMember($user, $project->community)) {
            return false;
        }

        return (int) $project->proposer_id === (int) $user->id
            || CommunityPivotAdmin::isRootOrPivotAdmin($user, $project->community);
    }

    public function delete(User $user, CommunityProject $project): bool
    {
        return $this->update($user, $project);
    }

    private function isMember(User $user, Community $community): bool
    {
        return $user->communities()->where('communities.id', $community->id)->exists();
    }
}
