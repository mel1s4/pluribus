<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\CapabilityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Session user payload (login + /api/user) including effective capabilities.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolver = app(CapabilityResolver::class);
        $memberships = $this->communities()
            ->orderBy('communities.name')
            ->get(['communities.id', 'communities.name', 'communities.slug'])
            ->map(function ($community): array {
                return [
                    'id' => $community->id,
                    'name' => $community->name,
                    'slug' => $community->slug,
                    'role' => $community->pivot?->role,
                ];
            })
            ->values();
        $activeCommunity = request()?->attributes?->get('active_community');
        $activeCommunityId = $activeCommunity instanceof \App\Models\Community ? (int) $activeCommunity->id : null;

        $externalLinks = is_array($this->external_links) ? $this->external_links : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'voting_id' => $this->voting_id,
            'phone_numbers' => is_array($this->phone_numbers) ? $this->phone_numbers : [],
            'contact_emails' => is_array($this->contact_emails) ? $this->contact_emails : [],
            'aliases' => is_array($this->aliases) ? $this->aliases : [],
            'external_links' => array_map(
                static fn (mixed $row): array => is_array($row)
                    ? [
                        'title' => (string) ($row['title'] ?? ''),
                        'url' => (string) ($row['url'] ?? ''),
                    ]
                    : ['title' => '', 'url' => ''],
                $externalLinks
            ),
            'avatar_url' => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
            'is_root' => (bool) $this->is_root,
            'user_type' => $this->user_type,
            'system_role' => $this->is_root ? 'root' : ($this->user_type === 'admin' ? 'admin' : null),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'capabilities' => $resolver->forUser($this->resource),
            'communities' => $memberships,
            'community_count' => $memberships->count(),
            'active_community_id' => $activeCommunityId,
        ];
    }
}
