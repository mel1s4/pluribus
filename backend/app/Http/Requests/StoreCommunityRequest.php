<?php

namespace App\Http\Requests;

use App\Models\Community;
use App\Support\LocaleOptions;
use App\Support\ReservedCommunitySlugs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $name = (string) $this->input('name', '');
        if (! $this->has('slug') || $this->input('slug') === '') {
            $this->merge(['slug' => Community::slugFromName($name)]);
        }

        foreach (['description', 'rules', 'logo'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('communities', 'slug'),
                Rule::notIn(ReservedCommunitySlugs::all()),
            ],
            'description' => ['nullable', 'string'],
            'rules' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'default_language' => ['sometimes', 'string', 'in:'.implode(',', LocaleOptions::codes())],
            'currency_code' => ['nullable', 'string', 'max:4'],
            'domains' => ['sometimes', 'array'],
            'domains.*.host' => ['required_with:domains', 'string', 'max:255'],
            'domains.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
