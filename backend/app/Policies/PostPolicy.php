<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return Post::query()
            ->whereKey($post->id)
            ->visibleToUser((int) $user->id)
            ->exists();
    }

    public function update(User $user, Post $post): bool
    {
        if ((int) $post->author_id === (int) $user->id) {
            return true;
        }

        if ($post->shared_group_id === null) {
            return false;
        }

        return $post->sharedGroup()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->exists();
    }

    public function delete(User $user, Post $post): bool
    {
        return (int) $post->author_id === (int) $user->id;
    }
}

