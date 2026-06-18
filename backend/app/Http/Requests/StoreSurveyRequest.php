<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSurveyRequest extends FormRequest
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
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'closes_at' => ['nullable', 'date'],
            'allow_multiple' => ['sometimes', 'boolean'],
            'require_ranked' => ['sometimes', 'boolean'],
            'allow_add_options' => ['sometimes', 'boolean'],
            'options' => ['required', 'array', 'min:2', 'max:10'],
            'options.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $options = $this->input('options', []);
            if (! is_array($options)) {
                return;
            }
            $normalized = array_map(
                static fn (mixed $label): string => mb_strtolower(trim((string) $label)),
                $options
            );
            if (count($normalized) !== count(array_unique($normalized))) {
                $validator->errors()->add('options', 'Each option must be unique.');
            }
        });
    }
}
