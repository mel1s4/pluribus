<?php

namespace App\Http\Resources;

use App\Models\Place;
use App\Support\PlaceMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal place card for member directory / profile.
 *
 * @mixin Place
 */
class PlaceDirectoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'tags' => $this->tags ?? [],
            'logo_url' => PlaceMedia::publicUrl($this->logo_path),
            'logo_background_color' => $this->logo_background_color,
        ];
    }
}
