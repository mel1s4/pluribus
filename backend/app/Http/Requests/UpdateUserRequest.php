<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\UserProfileContactNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone_numbers')) {
            $this->merge([
                'phone_numbers' => UserProfileContactNormalizer::stringList($this->input('phone_numbers')),
            ]);
        }
        if ($this->has('contact_emails')) {
            $this->merge([
                'contact_emails' => UserProfileContactNormalizer::stringList($this->input('contact_emails')),
            ]);
        }
        if ($this->has('aliases')) {
            $this->merge([
                'aliases' => UserProfileContactNormalizer::stringList($this->input('aliases')),
            ]);
        }
        if ($this->has('external_links')) {
            $this->merge([
                'external_links' => UserProfileContactNormalizer::externalLinks($this->input('external_links')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $actor = $this->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($target->id)],
            'phone_numbers' => ['sometimes', 'nullable', 'array', 'max:'.UserProfileContactNormalizer::MAX_STRING_LIST_ITEMS],
            'phone_numbers.*' => ['string', 'max:64'],
            'contact_emails' => ['sometimes', 'nullable', 'array', 'max:'.UserProfileContactNormalizer::MAX_STRING_LIST_ITEMS],
            'contact_emails.*' => ['string', 'email', 'max:255'],
            'aliases' => ['sometimes', 'nullable', 'array', 'max:'.UserProfileContactNormalizer::MAX_STRING_LIST_ITEMS],
            'aliases.*' => ['string', 'max:255'],
            'external_links' => ['sometimes', 'nullable', 'array', 'max:'.UserProfileContactNormalizer::MAX_EXTERNAL_LINKS],
            'external_links.*.title' => ['required', 'string', 'max:255'],
            'external_links.*.url' => ['required', 'string', 'url', 'max:2048'],
        ];

        if ($actor && $actor->can('users.assign_types')) {
            $allowedTypes = User::ASSIGNABLE_USER_TYPES;
            if ($target->is_root) {
                $allowedTypes[] = 'root';
            }
            $rules['user_type'] = ['sometimes', 'string', Rule::in($allowedTypes)];
            $rules['is_root'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.unique' => 'This username is already taken.',
        ];
    }
}
