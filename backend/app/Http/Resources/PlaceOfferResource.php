<?php

namespace App\Http\Resources;

use App\Support\PlaceMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PlaceOffer
 */
class PlaceOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var list<string>|null $gallery */
        $gallery = $this->gallery_paths;
        /** @var list<string>|null $tags */
        $tags = $this->tags;

        return [
            'id' => $this->id,
            'place_id' => $this->place_id,
            'sku' => $this->sku,
            'title' => $this->title,
            'description' => $this->description,
            'price' => (string) $this->price,
            'photo_path' => $this->photo_path,
            'photo_url' => PlaceMedia::publicUrl($this->photo_path),
            'gallery_paths' => $gallery ?? [],
            'gallery_urls' => collect($gallery ?? [])->map(fn (string $p) => PlaceMedia::publicUrl($p))->filter()->values()->all(),
            'tags' => $tags ?? [],
            'visibility_scope' => $this->visibility_scope,
            'audience_ids' => $this->relationLoaded('audiences')
                ? $this->audiences->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
