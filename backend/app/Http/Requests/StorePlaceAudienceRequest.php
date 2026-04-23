<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlaceAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'user_ids' => ['nullable', 'array', 'max:2000'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
