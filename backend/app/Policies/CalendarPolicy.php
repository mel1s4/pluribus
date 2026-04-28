<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\User;

class CalendarPolicy
{
    public function view(User $user, Calendar $calendar): bool
    {
        return Calendar::query()
            ->whereKey($calendar->id)
            ->visibleToUser((int) $user->id)
            ->exists();
    }

    public function update(User $user, Calendar $calendar): bool
    {
        if ((int) $calendar->owner_id === (int) $user->id) {
            return true;
        }

        if ($calendar->shared_group_id === null) {
            return false;
        }

        return $calendar->sharedGroup()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->exists();
    }

    public function delete(User $user, Calendar $calendar): bool
    {
        return (int) $calendar->owner_id === (int) $user->id;
    }
}

