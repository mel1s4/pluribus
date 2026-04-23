<?php

namespace App\Http\Requests;

use App\Support\LocaleOptions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSingletonCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['description', 'rules'] as $key) {
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rules' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'logo_upload' => ['nullable', 'file', 'image', 'max:5120'],
            'remove_logo' => ['sometimes', 'boolean'],
            'default_language' => ['sometimes', 'string', 'in:'.implode(',', LocaleOptions::codes())],
        ];
    }
}
