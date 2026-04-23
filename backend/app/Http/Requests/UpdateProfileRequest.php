<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\UserProfileContactNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('profile.update');
    }

    protected function prepareForValidation(): void
    {
        $password = $this->input('password');
        if (! is_string($password) || trim($password) === '') {
            $this->merge([
                'password' => null,
                'password_confirmation' => null,
                'current_password' => null,
            ]);
        }

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
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
            'current_password' => ['nullable', 'required_with:password', 'current_password:web'],
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
