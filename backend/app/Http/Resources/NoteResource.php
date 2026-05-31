<?php

namespace App\Http\Resources;

use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** @mixin Note */
class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'author_id' => $this->author_id,
            'title' => $this->title,
            'description' => $this->description,
            'content_markdown' => $this->content_markdown,
            'tags' => $this->tags ?? [],
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'folder' => $this->whenLoaded('folder', fn () => $this->folder ? new FolderResource($this->folder) : null),
            'last_edited_by' => $this->whenLoaded('lastEditedBy', function () {
                if (! $this->lastEditedBy) {
                    return null;
                }

                return $this->userStub($this->lastEditedBy);
            }),
            'revision_count' => $this->when(
                isset($this->resource->revisions_count),
                fn (): int => (int) $this->resource->revisions_count
            ),
            'can_edit' => $user instanceof User ? Gate::forUser($user)->allows('update', $this->resource) : false,
            'can_delete' => $user instanceof User ? Gate::forUser($user)->allows('delete', $this->resource) : false,
            'can_manage_collaborators' => $user instanceof User ? Gate::forUser($user)->allows('manageCollaborators', $this->resource) : false,
            'collaborators' => $this->whenLoaded('collaborators', function () {
                return $this->collaborators->map(fn (User $u): array => [
                    'user' => $this->userStub($u),
                    'permission' => (string) $u->pivot->permission,
                ])->values()->all();
            }),
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
