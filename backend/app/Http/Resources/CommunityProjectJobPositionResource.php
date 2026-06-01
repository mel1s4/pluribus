<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CommunityProjectJobPosition */
class CommunityProjectJobPositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'sort_order' => $this->sort_order,
            'tasks' => $this->whenLoaded(
                'tasks',
                fn () => CommunityProjectJobTaskResource::collection($this->tasks)->resolve($request)
            ),
        ];
    }
}
