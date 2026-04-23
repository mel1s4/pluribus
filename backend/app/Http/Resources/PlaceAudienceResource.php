<?php

namespace App\Http\Resources;

use App\Models\PlaceAudience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlaceAudience
 */
class PlaceAudienceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'place_id' => $this->place_id,
            'name' => $this->name,
            'members' => $this->whenLoaded(
                'members',
                fn () => CommunityMemberPickerResource::collection($this->members),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
