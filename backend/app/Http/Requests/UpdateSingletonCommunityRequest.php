<?php

namespace App\Http\Requests;

use App\Support\LocaleOptions;
use App\Support\LocalCurrencyOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSingletonCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['description', 'rules'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merge[$key] = null;
            }
        }
        foreach (['latitude', 'longitude'] as $key) {
            if ($this->has($key) && ($this->input($key) === '' || $this->input($key) === null)) {
                $merge[$key] = null;
            }
        }
        if ($this->has('currency_code') && $this->input('currency_code') === '') {
            $merge['currency_code'] = null;
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rules' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'logo_upload' => ['nullable', 'file', 'image', 'max:5120'],
            'remove_logo' => ['sometimes', 'boolean'],
            'default_language' => ['sometimes', 'string', 'in:'.implode(',', LocaleOptions::codes())],
            'currency_code' => ['nullable', 'string', 'max:4'],
            'currency_name' => ['nullable', 'string', 'max:64'],
            'local_currency_code' => ['nullable', 'string', Rule::in(LocalCurrencyOptions::codes())],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }
}
