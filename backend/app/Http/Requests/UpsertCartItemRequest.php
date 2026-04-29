<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertCartItemRequest extends FormRequest
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
            'place_offer_id' => ['required', 'integer', 'exists:place_offers,id'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
