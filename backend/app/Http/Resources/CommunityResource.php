<?php

namespace App\Http\Resources;

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
        $domains = $this->relationLoaded('domains')
            ? $this->domains
            : $this->domains()->orderByDesc('is_primary')->orderBy('id')->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'rules' => $this->rules,
            'terms_markdown' => $this->terms_markdown,
            'privacy_policy_markdown' => $this->privacy_policy_markdown,
            'logo' => $this->logo,
            'logo_url' => $this->publicLogoUrl(),
            'default_language' => $this->default_language,
            'currency_code' => $this->currency_code,
            'currency_name' => $this->currency_name,
            'local_currency_code' => $this->local_currency_code,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'domains' => $domains->map(fn ($domain): array => [
                'id' => $domain->id,
                'host' => $domain->host,
                'is_primary' => (bool) $domain->is_primary,
                'verified_at' => $domain->verified_at?->toIso8601String(),
            ])->values()->all(),
            'public_site_url' => $this->publicSiteUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
