<?php

namespace App\Policies;

use App\Models\Chat;
use App\Models\User;

class ChatPolicy
{
    public function view(User $user, Chat $chat): bool
    {
        return $chat->members()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Chat $chat): bool
    {
        return $chat->isOwner($user);
    }

    public function delete(User $user, Chat $chat): bool
    {
        return $chat->isOwner($user);
    }
}
