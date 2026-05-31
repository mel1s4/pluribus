<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'content_markdown' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'editor_session_id' => ['required', 'uuid'],
        ];
    }
}
