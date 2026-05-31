<?php

namespace App\Http\Resources;

use App\Models\NoteRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin NoteRevision */
class NoteRevisionDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $editedBy = $this->relationLoaded('editedBy') ? $this->editedBy : null;

        return [
            'id' => $this->id,
            'note_id' => $this->note_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_by' => $editedBy ? [
                'id' => $editedBy->id,
                'name' => $editedBy->name,
                'avatar_url' => $editedBy->avatar_path
                    ? Storage::disk('public')->url($editedBy->avatar_path)
                    : null,
            ] : null,
            'title' => $this->title,
            'description' => $this->description,
            'tags' => $this->tags ?? [],
            'content_markdown' => $this->content_markdown,
        ];
    }
}
