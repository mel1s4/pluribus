<?php

namespace App\Http\Requests;

use App\Support\LocalCurrencyOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('currency_code')) {
            $raw = $this->input('currency_code');
            if ($raw === '' || $raw === null) {
                $merge['currency_code'] = null;
            } elseif (is_string($raw)) {
                $trim = trim($raw);
                $merge['currency_code'] = $trim === '' ? null : substr($trim, 0, 4);
            }
        }
        if ($this->has('currency_name')) {
            $raw = $this->input('currency_name');
            if ($raw === '' || $raw === null) {
                $merge['currency_name'] = null;
            } elseif (is_string($raw)) {
                $trim = trim($raw);
                $merge['currency_name'] = $trim === '' ? null : substr($trim, 0, 64);
            }
        }
        if ($this->has('local_currency_code')) {
            $raw = $this->input('local_currency_code');
            if ($raw === '' || $raw === null) {
                $merge['local_currency_code'] = null;
            } elseif (is_string($raw)) {
                $merge['local_currency_code'] = strtoupper(trim($raw));
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency_code' => ['sometimes', 'nullable', 'string', 'max:4'],
            'currency_name' => ['sometimes', 'nullable', 'string', 'max:64'],
            'local_currency_code' => ['sometimes', 'nullable', 'string', Rule::in(LocalCurrencyOptions::codes())],
        ];
    }
}
