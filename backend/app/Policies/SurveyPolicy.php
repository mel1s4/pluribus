<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\Survey;
use App\Models\User;
use App\Support\CommunityPivotAdmin;

class SurveyPolicy
{
    public function viewAny(User $user, Community $community): bool
    {
        return $user->can('surveys.view') && $this->isMember($user, $community);
    }

    public function view(User $user, Survey $survey): bool
    {
        if (! $user->can('surveys.view')) {
            return false;
        }

        return $this->isMember($user, $survey->community);
    }

    public function create(User $user, Community $community): bool
    {
        if (! $user->can('surveys.manage')) {
            return false;
        }

        if (! $this->isMember($user, $community)) {
            return false;
        }

        return $user->hasVotingId();
    }

    public function update(User $user, Survey $survey): bool
    {
        if (! $user->can('surveys.manage')) {
            return false;
        }

        if (! $this->isMember($user, $survey->community)) {
            return false;
        }

        return (int) $survey->author_id === (int) $user->id
            || CommunityPivotAdmin::isRootOrPivotAdmin($user, $survey->community);
    }

    public function delete(User $user, Survey $survey): bool
    {
        return $this->update($user, $survey);
    }

    public function vote(User $user, Survey $survey): bool
    {
        if (! $this->view($user, $survey)) {
            return false;
        }

        if (! $user->hasVotingId()) {
            return false;
        }

        return $survey->isOpen();
    }

    private function isMember(User $user, Community $community): bool
    {
        return $user->communities()->where('communities.id', $community->id)->exists();
    }
}
