<?php

namespace App\Policies;

use App\Models\Place;
use App\Models\User;

class PlacePolicy
{
    public function view(User $user, Place $place): bool
    {
        return $place->roleForUser($user) !== '';
    }

    public function update(User $user, Place $place): bool
    {
        $role = $place->roleForUser($user);

        return in_array($role, ['owner', Place::ADMIN_ROLE_FULL_ACCESS, Place::ADMIN_ROLE_EDITOR], true);
    }

    public function delete(User $user, Place $place): bool
    {
        return $this->update($user, $place);
    }

    /**
     * Add/remove administrators and assign Full access vs Editor roles.
     * Only the place owner and Full access administrators may do this.
     */
    public function manageAdmins(User $user, Place $place): bool
    {
        $role = $place->roleForUser($user);

        return $role === 'owner' || $role === Place::ADMIN_ROLE_FULL_ACCESS;
    }
}
