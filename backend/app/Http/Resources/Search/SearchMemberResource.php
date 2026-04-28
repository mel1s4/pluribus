<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SearchMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $slug = $this->username ?: ($this->profile_slug ?: (string) $this->id);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'subtitle' => $this->username ? '@'.$this->username : $this->email,
            'tags' => [],
            'avatar_url' => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
            'to' => '/members/'.$slug,
        ];
    }
}
