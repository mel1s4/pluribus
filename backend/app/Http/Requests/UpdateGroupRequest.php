<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'owner_id' => ['sometimes', 'required', 'integer', Rule::exists('group_members', 'user_id')->where(
                fn ($q) => $q->where('group_id', (int) optional($this->route('group'))->id)
            )],
            'owner_role' => ['sometimes', 'nullable', Rule::in(Group::ROLES)],
        ];
    }
}

