<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Public leadership card for a user (no email).
 *
 * @mixin User
 */
class CommunityLeaderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isRoot = (bool) $this->is_root;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar_url' => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
            'is_root' => $isRoot,
            'user_type' => $this->user_type,
            'role_label_key' => $isRoot ? 'root' : (string) $this->user_type,
        ];
    }
}
