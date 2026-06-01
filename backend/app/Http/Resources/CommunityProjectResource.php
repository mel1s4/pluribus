<?php

namespace App\Http\Resources;

use App\Models\Place;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\CommunityProject */
class CommunityProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'proposer_id' => $this->proposer_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'deadline' => $this->deadline?->toIso8601String(),
            'has_budget' => (bool) $this->has_budget,
            'has_job_positions' => (bool) $this->has_job_positions,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_type' => $this->location_type ?? Place::LOCATION_NONE,
            'service_area_type' => $this->service_area_type ?? Place::SERVICE_AREA_NONE,
            'radius_meters' => $this->radius_meters,
            'area_geojson' => $this->area_geojson,
            'budget_total' => $this->resolveBudgetTotal(),
            'budget_items' => $this->whenLoaded(
                'budgetItems',
                fn () => CommunityProjectBudgetItemResource::collection($this->budgetItems)->resolve($request)
            ),
            'job_positions' => $this->whenLoaded(
                'jobPositions',
                fn () => CommunityProjectJobPositionResource::collection($this->jobPositions)->resolve($request)
            ),
            'community' => $this->whenLoaded('community', fn () => (new CommunityResource($this->community))->toArray($request)),
            'proposer' => $this->whenLoaded('proposer', fn () => $this->proposer ? $this->userStub($this->proposer) : null),
            'can_update' => $user instanceof User ? Gate::forUser($user)->allows('update', $this->resource) : false,
            'can_delete' => $user instanceof User ? Gate::forUser($user)->allows('delete', $this->resource) : false,
        ];
    }

    private function resolveBudgetTotal(): string
    {
        if (array_key_exists('budget_items_sum_subtotal', $this->resource->getAttributes())) {
            $v = $this->resource->getAttribute('budget_items_sum_subtotal');

            return $v === null ? '0.00' : (string) $v;
        }
        if ($this->relationLoaded('budgetItems')) {
            $total = 0.0;
            foreach ($this->budgetItems as $row) {
                $total += (float) $row->subtotal;
            }

            return number_format($total, 2, '.', '');
        }

        return '0.00';
    }

    /**
     * @return array<string, mixed>
     */
    private function userStub(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'avatar_url' => $u->avatar_path
                ? Storage::disk('public')->url($u->avatar_path)
                : null,
        ];
    }
}
