<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $externalLinks = is_array($this->external_links) ? $this->external_links : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'profile_slug' => $this->profile_slug,
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
            'is_root' => (bool) $this->is_root,
            'user_type' => $this->user_type,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
