<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        $actor = $this->user();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
        ];

        if ($actor && $actor->can('users.assign_types')) {
            $rules['user_type'] = ['sometimes', 'string', Rule::in(User::ASSIGNABLE_USER_TYPES)];
            $rules['is_root'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
