<?php

namespace App\Http\Resources;

use App\Models\Community;
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
        $community = Community::current();
        $localCurrencyCode = $community->local_currency_code;

        return [
            'id' => $this->id,
            'place_id' => $this->place_id,
            'sku' => $this->sku,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price !== null ? (string) $this->price : null,
            'local_price' => $this->local_price !== null ? (string) $this->local_price : null,
            'local_currency_code' => is_string($localCurrencyCode) && $localCurrencyCode !== ''
                ? $localCurrencyCode
                : null,
            'photo_path' => $this->photo_path,
            'photo_url' => PlaceMedia::publicUrl($this->photo_path),
            'gallery_paths' => $gallery ?? [],
            'gallery_urls' => collect($gallery ?? [])->map(fn (string $p) => PlaceMedia::publicUrl($p))->filter()->values()->all(),
            'tags' => $tags ?? [],
            'category' => $this->category,
            'visibility_scope' => $this->visibility_scope,
            'audience_ids' => $this->relationLoaded('audiences')
                ? $this->audiences->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
