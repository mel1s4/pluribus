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
            'thesis_title' => $this->thesis_title,
            'thesis_body' => $this->thesis_body,
            'status' => $this->status,
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
            'budget_sum' => $this->resolveBudgetSum(),
            'budget_items' => $this->whenLoaded(
                'budgetItems',
                fn () => CommunityProjectBudgetItemResource::collection($this->budgetItems)->resolve($request)
            ),
            'community' => $this->whenLoaded('community', fn () => (new CommunityResource($this->community))->toArray($request)),
            'proposer' => $this->whenLoaded('proposer', fn () => $this->proposer ? $this->userStub($this->proposer) : null),
            'arguments' => $this->whenLoaded('arguments', function () use ($request) {
                return $this->arguments
                    ->map(fn ($arg) => (new ProjectArgumentResource($arg))->toArray($request))
                    ->values()
                    ->all();
            }),
            'can_update' => $user instanceof User ? Gate::forUser($user)->allows('update', $this->resource) : false,
            'can_delete' => $user instanceof User ? Gate::forUser($user)->allows('delete', $this->resource) : false,
            'is_proposer' => $this->when(
                array_key_exists('viewer_is_proposer', $this->resource->getAttributes()),
                (bool) (int) $this->resource->getAttribute('viewer_is_proposer')
            ),
            'has_argued' => $this->when(
                array_key_exists('viewer_has_argued', $this->resource->getAttributes()),
                (bool) (int) $this->resource->getAttribute('viewer_has_argued')
            ),
        ];
    }

    private function resolveBudgetSum(): string
    {
        if (array_key_exists('budget_items_sum_cost', $this->resource->getAttributes())) {
            $v = $this->resource->getAttribute('budget_items_sum_cost');

            return $v === null ? '0.00' : (string) $v;
        }
        if ($this->relationLoaded('budgetItems')) {
            $total = 0.0;
            foreach ($this->budgetItems as $row) {
                $total += (float) $row->cost;
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
