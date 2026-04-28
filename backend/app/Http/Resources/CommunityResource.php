<?php

namespace App\Http\Resources;

use App\Support\PlaceMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Community
 */
class CommunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'rules' => $this->rules,
            'logo' => $this->logo,
            'logo_url' => $this->resolveLogoUrl($this->logo),
            'default_language' => $this->default_language,
            'currency_code' => $this->currency_code,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveLogoUrl(?string $logo): ?string
    {
        if ($logo === null || $logo === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $logo) === 1) {
            return $logo;
        }

        return PlaceMedia::publicUrl($logo);
    }
}
