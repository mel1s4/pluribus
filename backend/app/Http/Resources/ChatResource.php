<?php

namespace App\Http\Resources;

use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Chat */
class ChatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'owner_id' => $this->owner_id,
            'type' => $this->type,
            'title' => $this->title,
            'icon_emoji' => $this->icon_emoji,
            'icon_bg_color' => $this->icon_bg_color,
            'folder_id' => $this->folder_id,
            'is_owner' => (int) ($request->user()?->id ?? 0) === (int) $this->owner_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'avatar_path' => $member->avatar_path,
            ])->values()),
            'folder' => $this->whenLoaded('folder', fn () => $this->folder ? new ChatFolderResource($this->folder) : null),
            'last_message_at' => $this->when(isset($this->last_message_at), fn () => $this->last_message_at),
            'unread_count' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
        ];
    }
}
