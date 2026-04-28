<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
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
            'folder_id' => ['sometimes', 'nullable', 'integer', 'exists:chat_folders,id'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'highlighted' => ['sometimes', 'boolean'],
            'completed_at' => ['sometimes', 'nullable', 'date'],

            // Post-backed fields
            'shared_group_id' => ['sometimes', 'nullable', 'integer', 'exists:groups,id'],
            'calendar_id' => ['sometimes', 'nullable', 'integer', 'exists:calendars,id'],
            'place_id' => ['sometimes', 'nullable', 'integer', 'exists:places,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'content_markdown' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'start_at' => ['sometimes', 'nullable', 'date'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_at'],
            'all_day' => ['sometimes', 'boolean'],
            'recurrence_rule' => ['sometimes', 'nullable', 'string'],
            'recurrence_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'visibility_scope' => ['sometimes', 'required', 'string', 'in:private,community,group'],
        ];
    }
}

