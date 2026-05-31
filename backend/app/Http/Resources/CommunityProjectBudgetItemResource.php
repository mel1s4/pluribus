<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CommunityProjectBudgetItem */
class CommunityProjectBudgetItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_project_id' => $this->community_project_id,
            'name' => $this->name,
            'description' => $this->description,
            'cost' => (string) $this->cost,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
