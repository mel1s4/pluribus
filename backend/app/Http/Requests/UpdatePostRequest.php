<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
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
            'shared_group_id' => ['sometimes', 'nullable', 'integer', 'exists:groups,id'],
            'calendar_id' => ['sometimes', 'nullable', 'integer', 'exists:calendars,id'],
            'place_id' => ['sometimes', 'nullable', 'integer', 'exists:places,id'],
            'type' => ['sometimes', 'required', Rule::in(Post::TYPES)],
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
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'influence_area_type' => ['sometimes', 'nullable', Rule::in(Post::INFLUENCE_AREA_TYPES)],
            'influence_radius_meters' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'influence_area_geojson' => ['sometimes', 'nullable', 'array'],
            'visibility_scope' => ['sometimes', 'required', Rule::in(Post::VISIBILITY_SCOPES)],
        ];
    }
}

