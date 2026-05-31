<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\ProjectArgument */
class ProjectArgumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'parent_id' => $this->parent_id,
            'stance' => $this->stance,
            'title' => $this->title,
            'body' => $this->body,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => $this->author ? $this->userStub($this->author) : null),
            'can_update' => $user instanceof User ? Gate::forUser($user)->allows('update', $this->resource) : false,
            'can_delete' => $user instanceof User ? Gate::forUser($user)->allows('delete', $this->resource) : false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userStub(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'avatar_url' => $u->avatar_path
                ? Storage::disk('public')->url($u->avatar_path)
                : null,
        ];
    }
}
