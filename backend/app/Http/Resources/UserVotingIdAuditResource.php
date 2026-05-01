<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserVotingIdAudit
 */
class UserVotingIdAuditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $actor */
        $actor = $this->changedBy;

        return [
            'id' => $this->id,
            'old_voting_id' => $this->old_voting_id,
            'new_voting_id' => $this->new_voting_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'changed_by' => $actor !== null
                ? ['id' => $actor->id, 'name' => $actor->name]
                : null,
        ];
    }
}
