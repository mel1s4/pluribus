<?php

namespace App\Http\Resources;

use App\Models\Calendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Calendar */
class CalendarResource extends JsonResource
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
            'shared_group_id' => $this->shared_group_id,
            'name' => $this->name,
            'color' => $this->color,
            'visibility_scope' => $this->visibility_scope,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

