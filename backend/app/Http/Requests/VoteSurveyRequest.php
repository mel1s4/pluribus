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
            'option_id' => ['required', 'integer'],
        ];
    }
}
