<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return Task::query()
            ->whereKey($task->id)
            ->visibleToUser((int) $user->id)
            ->exists();
    }

    public function update(User $user, Task $task): bool
    {
        return (int) $task->author_id === (int) $user->id
            || ((int) $task->assignee_id === (int) $user->id);
    }

    public function delete(User $user, Task $task): bool
    {
        return (int) $task->author_id === (int) $user->id;
    }
}

