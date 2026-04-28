<?php

namespace App\Http\Resources\Search;

use App\Support\PlaceMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchPlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $target = $this->slug ? '/place/'.$this->slug : '/places/'.$this->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'subtitle' => $this->description,
            'tags' => is_array($this->tags) ? $this->tags : [],
            'image_url' => PlaceMedia::publicUrl($this->logo_path),
            'to' => $target,
        ];
    }
}
