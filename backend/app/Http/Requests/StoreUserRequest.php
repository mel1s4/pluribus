<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\UserProfileContactNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_numbers' => UserProfileContactNormalizer::stringList($this->input('phone_numbers')),
            'contact_emails' => UserProfileContactNormalizer::stringList($this->input('contact_emails')),
            'aliases' => UserProfileContactNormalizer::stringList($this->input('aliases')),
            'external_links' => UserProfileContactNormalizer::externalLinks($this->input('external_links')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'phone_numbers' => ['nullable', 'array', 'max:'.UserProfileContactNormalizer::MAX_STRING_LIST_ITEMS],
            'phone_numbers.*' => ['string', 'max:64'],
            'contact_emails' => ['nullable', 'array', 'max:'.UserProfileContactNormalizer::MAX_STRING_LIST_ITEMS],
            'contact_emails.*' => ['string', 'email', 'max:255'],
            'aliases' => ['nullable', 'array', 'max:'.UserProfileContactNormalizer::MAX_STRING_LIST_ITEMS],
            'aliases.*' => ['string', 'max:255'],
            'external_links' => ['nullable', 'array', 'max:'.UserProfileContactNormalizer::MAX_EXTERNAL_LINKS],
            'external_links.*.title' => ['required', 'string', 'max:255'],
            'external_links.*.url' => ['required', 'string', 'url', 'max:2048'],
        ];

        if ($actor && $actor->can('users.assign_types')) {
            $rules['user_type'] = ['sometimes', 'string', Rule::in(User::ASSIGNABLE_USER_TYPES)];
            $rules['is_root'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
