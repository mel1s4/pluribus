<?php

namespace App\Policies;

use App\Models\CommunityProject;
use App\Models\ProjectArgument;
use App\Models\User;
use App\Support\CommunityPivotAdmin;

class ProjectArgumentPolicy
{
    public function view(User $user, ProjectArgument $argument): bool
    {
        $project = $argument->project;
        $project->loadMissing('community');

        return $user->communities()->where('communities.id', $project->community_id)->exists();
    }

    public function create(User $user, CommunityProject $project): bool
    {
        $project->loadMissing('community');

        return $user->communities()->where('communities.id', $project->community_id)->exists();
    }

    public function update(User $user, ProjectArgument $argument): bool
    {
        if (! $this->view($user, $argument)) {
            return false;
        }

        $argument->loadMissing('project.community');

        return (int) $argument->author_id === (int) $user->id
            || CommunityPivotAdmin::isRootOrPivotAdmin($user, $argument->project->community);
    }

    public function delete(User $user, ProjectArgument $argument): bool
    {
        return $this->update($user, $argument);
    }
}
