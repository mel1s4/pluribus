<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Task */
class TaskResource extends JsonResource
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
            'folder_id' => $this->folder_id,
            'folder' => $this->whenLoaded('folder', fn () => $this->folder ? new FolderResource($this->folder) : null),
            'assignee_id' => $this->assignee_id,
            'assignee' => $this->whenLoaded('assignee', function () {
                if (! $this->assignee) {
                    return null;
                }

                return [
                    'id' => $this->assignee->id,
                    'name' => $this->assignee->name,
                    'avatar_url' => $this->assignee->avatar_path
                        ? Storage::disk('public')->url($this->assignee->avatar_path)
                        : null,
                ];
            }),
            'title' => $this->title,
            'description' => $this->description,
            'content_markdown' => $this->content_markdown,
            'tags' => $this->tags ?? [],
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'all_day' => (bool) $this->all_day,
            'recurrence_rule' => $this->recurrence_rule,
            'recurrence_id' => $this->recurrence_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'position' => $this->position,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'highlighted' => (bool) $this->highlighted,
            'visibility_scope' => $this->visibility_scope,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
