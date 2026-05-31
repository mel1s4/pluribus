<?php

namespace App\Http\Requests;

use App\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'folder_id' => ['required', 'integer', 'exists:folders,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content_markdown' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function validatedFolderVisibleTo(int $userId): void
    {
        $folderId = (int) $this->validated('folder_id');
        $ok = Folder::query()
            ->visibleToUser($userId)
            ->whereKey($folderId)
            ->exists();
        abort_unless($ok, 422, 'Invalid folder.');
    }
}
