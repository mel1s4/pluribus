<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResolvePersonificationUserRequest extends FormRequest
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
            'email' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $email = trim((string) $this->input('email', ''));
            $username = trim((string) $this->input('username', ''));
            if ($email === '' && $username === '') {
                $validator->errors()->add('email', 'Provide an email or username to resolve.');
            }
            if ($email !== '' && $username !== '') {
                $validator->errors()->add('email', 'Provide only one of email or username.');
            }
        });
    }
}
