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
            'name' => $this->name,
            'description' => $this->description,
            'unit_cost' => (string) $this->unit_cost,
            'units' => (string) $this->units,
            'subtotal' => (string) $this->subtotal,
            'sort_order' => $this->sort_order,
        ];
    }
}
