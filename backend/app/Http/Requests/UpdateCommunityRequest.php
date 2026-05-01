<?php

namespace App\Http\Requests;

use App\Models\Community;
use App\Support\LocaleOptions;
use App\Support\ReservedCommunitySlugs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('slug') || $this->input('slug') === '') {
            $name = (string) $this->input('name', '');
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
        /** @var Community|null $community */
        $community = $this->route('community');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('communities', 'slug')->ignore($community?->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($community): void {
                    $slug = (string) $value;
                    if (! ReservedCommunitySlugs::isReserved($slug)) {
                        return;
                    }
                    if ($community !== null && $slug === (string) $community->getAttribute('slug')) {
                        return;
                    }
                    $fail(__('This slug is reserved for system routes.'));
                },
            ],
            'description' => ['nullable', 'string'],
            'rules' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'default_language' => ['sometimes', 'string', 'in:'.implode(',', LocaleOptions::codes())],
            'currency_code' => ['nullable', 'string', 'max:4'],
        ];
    }
}
