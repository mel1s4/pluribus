<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoteLockRequest extends FormRequest
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
            'session_id' => ['required', 'uuid'],
        ];
    }
}
