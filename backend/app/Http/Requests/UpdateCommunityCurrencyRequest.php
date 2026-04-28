<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('currency_code')) {
            return;
        }
        $raw = $this->input('currency_code');
        if ($raw === '' || $raw === null) {
            $this->merge(['currency_code' => null]);

            return;
        }
        if (is_string($raw)) {
            $trim = trim($raw);
            $this->merge(['currency_code' => $trim === '' ? null : substr($trim, 0, 4)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency_code' => ['nullable', 'string', 'max:4'],
        ];
    }
}
