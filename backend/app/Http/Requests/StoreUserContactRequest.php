<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) ($this->user()?->id ?? 0);

        return [
            'contact_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$userId]),
            ],
        ];
    }
}
