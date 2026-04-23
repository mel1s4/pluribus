<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var User $target */
        $target = $this->route('user');
        $actor = $this->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($target->id)],
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
}
