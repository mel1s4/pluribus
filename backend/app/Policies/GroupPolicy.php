<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function view(User $user, Group $group): bool
    {
        return $group->isOwner($user) || $group->hasMember((int) $user->id);
    }

    public function update(User $user, Group $group): bool
    {
        if ($group->isOwner($user)) {
            return true;
        }

        return $group->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', Group::ROLE_ADMIN)
            ->exists();
    }

    public function delete(User $user, Group $group): bool
    {
        return $group->isOwner($user);
    }

    public function manageMembers(User $user, Group $group): bool
    {
        return $this->update($user, $group);
    }
}

