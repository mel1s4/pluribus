<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityDomainsRequest extends FormRequest
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
            'domains' => ['required', 'array'],
            'domains.*.host' => ['required', 'string', 'max:255'],
            'domains.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
