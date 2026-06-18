<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoteSurveyRequest extends FormRequest
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
            'option_id' => ['sometimes', 'integer'],
            'selections' => ['sometimes', 'array', 'min:1'],
            'selections.*.option_id' => ['sometimes', 'integer'],
            'selections.*.custom_label' => ['sometimes', 'string', 'max:255'],
            'selections.*.rank' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
