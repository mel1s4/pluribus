<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'shared_group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'calendar_id' => ['nullable', 'integer', 'exists:calendars,id'],
            'place_id' => ['nullable', 'integer', 'exists:places,id'],
            'type' => ['required', Rule::in([Post::TYPE_EVENT, Post::TYPE_ANNOUNCEMENT, Post::TYPE_INFO])],
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'influence_area_type' => ['nullable', Rule::in(Post::INFLUENCE_AREA_TYPES)],
            'influence_radius_meters' => ['nullable', 'integer', 'min:1'],
            'influence_area_geojson' => ['nullable', 'array'],
            'visibility_scope' => ['required', Rule::in(Post::VISIBILITY_SCOPES)],
        ];
    }
}

