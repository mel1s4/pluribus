<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $place = null;
        if ($this->relationLoaded('offer') && $this->offer !== null) {
            $p = $this->offer->relationLoaded('place') ? $this->offer->place : null;
            if ($p !== null) {
                $place = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                ];
            }
        }

        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'offer' => $this->when(
                $this->relationLoaded('offer') && $this->offer !== null,
                fn () => new PlaceOfferResource($this->offer)
            ),
            'place' => $place,
            'table' => $this->when(
                $this->relationLoaded('table') && $this->table !== null,
                fn () => new TableResource($this->table)
            ),
        ];
    }
}
