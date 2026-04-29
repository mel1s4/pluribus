<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\PlaceMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed>|null $snap */
        $snap = $this->offer_snapshot;

        return [
            'id' => $this->id,
            'place_offer_id' => $this->place_offer_id,
            'place_id' => $this->place_id,
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'subtotal' => (string) $this->subtotal,
            'offer_snapshot' => $snap ?? [],
            'snapshot_photo_url' => isset($snap['photo_path']) && is_string($snap['photo_path'])
                ? PlaceMedia::publicUrl($snap['photo_path'])
                : null,
            'place' => $this->when(
                $this->relationLoaded('place') && $this->place !== null,
                fn () => [
                    'id' => $this->place->id,
                    'name' => $this->place->name,
                    'slug' => $this->place->slug,
                ]
            ),
        ];
    }
}
