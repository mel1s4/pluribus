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
        $userVotes = $user instanceof User
            ? $this->votes->where('user_id', $user->id)->values()
            : collect();

        $userSelections = $userVotes->map(static function (SurveyVote $vote): array {
            $option = $vote->relationLoaded('option') ? $vote->option : null;

            return [
                'option_id' => (int) $vote->survey_option_id,
                'label' => $option?->label ?? '',
                'rank' => $vote->rank,
            ];
        })->values()->all();

        $firstVote = $userVotes->first();
        $userOptionId = $firstVote instanceof SurveyVote ? (int) $firstVote->survey_option_id : null;

        $totalSelections = (int) $this->options->sum('votes_count');
        $participantCount = (int) ($this->participant_count ?? 0);

        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'author_id' => $this->author_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'is_open' => $this->isOpen(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'allow_multiple' => (bool) $this->allow_multiple,
            'require_ranked' => (bool) $this->require_ranked,
            'allow_add_options' => (bool) $this->allow_add_options,
            'has_votes' => $this->hasVotes(),
            'total_votes' => $totalSelections,
            'participant_count' => $participantCount,
            'user_option_id' => $userOptionId,
            'user_has_voted' => $userVotes->isNotEmpty(),
            'user_selections' => $userSelections,
            'can_vote' => $user instanceof User ? $user->can('vote', $this->resource) : false,
            'can_manage' => $user instanceof User ? $user->can('update', $this->resource) : false,
            'options' => $this->options->map(function ($option) use ($totalSelections): array {
                $count = (int) ($option->votes_count ?? 0);
                $percent = $totalSelections > 0 ? round(($count / $totalSelections) * 100, 1) : 0.0;
                $averageRank = null;
                if ($this->require_ranked && $option->relationLoaded('votes')) {
                    $ranked = $option->votes->pluck('rank')->filter();
                    if ($ranked->isNotEmpty()) {
                        $averageRank = round($ranked->avg(), 2);
                    }
                }

                return [
                    'id' => $option->id,
                    'label' => $option->label,
                    'sort_order' => (int) $option->sort_order,
                    'is_custom' => (bool) $option->is_custom,
                    'vote_count' => $count,
                    'vote_percent' => $percent,
                    'average_rank' => $averageRank,
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
