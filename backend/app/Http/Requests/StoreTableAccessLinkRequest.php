<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTableAccessLinkRequest extends FormRequest
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
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
        ];
    }
}
