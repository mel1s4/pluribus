<?php

namespace App\Http\Resources;

use App\Models\Survey;
use App\Models\SurveyVote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Survey */
class SurveyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $userVoteOptionId = null;
        if ($user instanceof User) {
            $vote = $this->votes->firstWhere('user_id', $user->id);
            if ($vote instanceof SurveyVote) {
                $userVoteOptionId = (int) $vote->survey_option_id;
            }
        }

        $totalVotes = (int) $this->options->sum('votes_count');

        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'author_id' => $this->author_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'is_open' => $this->isOpen(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'has_votes' => $this->hasVotes(),
            'total_votes' => $totalVotes,
            'user_option_id' => $userVoteOptionId,
            'user_has_voted' => $userVoteOptionId !== null,
            'can_vote' => $user instanceof User ? $user->can('vote', $this->resource) : false,
            'can_manage' => $user instanceof User ? $user->can('update', $this->resource) : false,
            'options' => $this->options->map(static function ($option) use ($totalVotes): array {
                $count = (int) ($option->votes_count ?? 0);
                $percent = $totalVotes > 0 ? round(($count / $totalVotes) * 100, 1) : 0.0;

                return [
                    'id' => $option->id,
                    'label' => $option->label,
                    'sort_order' => (int) $option->sort_order,
                    'vote_count' => $count,
                    'vote_percent' => $percent,
                ];
            })->values()->all(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
