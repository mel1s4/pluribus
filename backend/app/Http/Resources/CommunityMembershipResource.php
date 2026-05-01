<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * User as a member of the active community (includes pivot role).
 *
 * @mixin User
 */
class CommunityMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = (string) ($this->pivot?->role ?? '');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
            'email' => $this->email,
            'username' => $this->username,
            'profile_slug' => $this->profile_slug,
            'membership_role' => $role,
            'is_root' => (bool) $this->is_root,
            'user_type' => $this->user_type,
        ];
    }
}
