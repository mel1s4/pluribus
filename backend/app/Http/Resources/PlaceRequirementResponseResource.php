<?php

namespace App\Http\Resources;

use App\Models\PlaceRequirementResponse;
use App\Support\PlaceMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlaceRequirementResponse
 */
class PlaceRequirementResponseResource extends JsonResource
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
            'place_requirement_id' => $this->place_requirement_id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => (string) $this->price,
            'photo_path' => $this->photo_path,
            'photo_url' => PlaceMedia::publicUrl($this->photo_path),
            'gallery_paths' => $gallery ?? [],
            'gallery_urls' => collect($gallery ?? [])->map(fn (string $p) => PlaceMedia::publicUrl($p))->filter()->values()->all(),
            'tags' => $tags ?? [],
            'visibility' => $this->visibility,
            'user' => $this->relationLoaded('user') && $this->user !== null
                ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ]
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
