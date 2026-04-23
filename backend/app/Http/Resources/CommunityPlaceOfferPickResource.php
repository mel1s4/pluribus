<?php

namespace App\Http\Resources;

use App\Models\PlaceOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlaceOffer
 */
class CommunityPlaceOfferPickResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new PlaceOfferResource($this->resource))->toArray($request);

        $placeBlock = null;
        if ($this->relationLoaded('place') && $this->place !== null) {
            $placeBlock = [
                'id' => $this->place->id,
                'name' => $this->place->name,
            ];
        }

        return array_merge($base, [
            'place' => $placeBlock,
        ]);
    }
}
