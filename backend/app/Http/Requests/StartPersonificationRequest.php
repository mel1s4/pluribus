<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartPersonificationRequest extends FormRequest
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
        return [
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'password' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:12', 'max:2000'],
            'ticket_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
