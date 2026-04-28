<?php

namespace App\Http\Requests;

use App\Models\Calendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalendarRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'visibility_scope' => ['sometimes', 'required', Rule::in(Calendar::VISIBILITY_SCOPES)],
            'shared_group_id' => ['sometimes', 'nullable', 'integer', 'exists:groups,id'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}

