<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityLegalDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['terms_markdown', 'privacy_policy_markdown'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merge[$key] = null;
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'terms_markdown' => ['sometimes', 'nullable', 'string', 'max:60000'],
            'privacy_policy_markdown' => ['sometimes', 'nullable', 'string', 'max:60000'],
        ];
    }
}
