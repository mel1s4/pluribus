<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'author_id' => $this->author_id,
            'shared_group_id' => $this->shared_group_id,
            'calendar_id' => $this->calendar_id,
            'place_id' => $this->place_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'content_markdown' => $this->content_markdown,
            'tags' => is_array($this->tags) ? $this->tags : [],
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'all_day' => (bool) $this->all_day,
            'recurrence_rule' => $this->recurrence_rule,
            'recurrence_id' => $this->recurrence_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'influence_area_type' => $this->influence_area_type,
            'influence_radius_meters' => $this->influence_radius_meters,
            'influence_area_geojson' => $this->influence_area_geojson,
            'visibility_scope' => $this->visibility_scope,
            'task' => $this->whenLoaded('task', fn () => new TaskResource($this->task)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

