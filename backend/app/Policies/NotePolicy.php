<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    public function view(User $user, Note $note): bool
    {
        return Note::query()
            ->whereKey($note->id)
            ->visibleToUser((int) $user->id)
            ->exists();
    }

    public function update(User $user, Note $note): bool
    {
        if (! $this->view($user, $note)) {
            return false;
        }

        return $note->userCanEdit($user);
    }

    public function delete(User $user, Note $note): bool
    {
        if (! $this->view($user, $note)) {
            return false;
        }

        return $note->isAuthor($user) || $note->isFolderOwner($user);
    }

    public function manageCollaborators(User $user, Note $note): bool
    {
        if (! $this->view($user, $note)) {
            return false;
        }

        return $note->userCanManageCollaborators($user);
    }

    public function revert(User $user, Note $note): bool
    {
        return $this->update($user, $note);
    }
}
