<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWalletGrantRequest extends FormRequest
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
            'community_id' => ['required', 'integer', 'exists:communities,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $uid = $this->input('user_id');
            $email = $this->input('email');
            $hasUser = $uid !== null && $uid !== '' && (int) $uid > 0;
            $hasEmail = is_string($email) && trim($email) !== '';
            if ($hasUser === $hasEmail) {
                $v->errors()->add('email', __('Provide exactly one of email or user_id.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => mb_strtolower(trim($this->input('email'))),
            ]);
        }
    }
}
