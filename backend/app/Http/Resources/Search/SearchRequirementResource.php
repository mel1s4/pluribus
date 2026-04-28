<?php

namespace App\Http\Resources\Search;

use App\Support\PlaceMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchRequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->title,
            'subtitle' => $this->place?->name,
            'tags' => is_array($this->tags) ? $this->tags : [],
            'image_url' => PlaceMedia::publicUrl($this->photo_path),
            'to' => '/places/'.$this->place_id,
        ];
    }
}
