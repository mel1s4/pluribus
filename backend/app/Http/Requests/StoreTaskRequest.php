<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'position' => ['nullable', 'integer', 'min:0'],
            'highlighted' => ['nullable', 'boolean'],
            'completed_at' => ['nullable', 'date'],

            'shared_group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'calendar_id' => ['nullable', 'integer', 'exists:calendars,id'],
            'place_id' => ['nullable', 'integer', 'exists:places,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content_markdown' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'all_day' => ['nullable', 'boolean'],
            'recurrence_rule' => ['nullable', 'string'],
            'recurrence_id' => ['nullable', 'string', 'max:100'],
            'visibility_scope' => ['nullable', Rule::in(Task::VISIBILITY_SCOPES)],
        ];
    }
}

