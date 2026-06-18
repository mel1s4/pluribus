<?php

namespace App\Http\Requests;

use App\Models\Survey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSurveyRequest extends FormRequest
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
        /** @var Survey $survey */
        $survey = $this->route('survey');
        $hasVotes = $survey instanceof Survey && $survey->hasVotes();

        $rules = [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(Survey::STATUSES)],
            'closes_at' => ['sometimes', 'nullable', 'date'],
        ];

        if (! $hasVotes) {
            $rules['allow_multiple'] = ['sometimes', 'boolean'];
            $rules['require_ranked'] = ['sometimes', 'boolean'];
            $rules['allow_add_options'] = ['sometimes', 'boolean'];
            $rules['options'] = ['sometimes', 'array', 'min:2', 'max:10'];
            $rules['options.*'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Survey|null $survey */
            $survey = $this->route('survey');
            if ($survey instanceof Survey && $survey->hasVotes()) {
                if ($this->has('options')) {
                    $validator->errors()->add('options', 'Options cannot be changed after votes have been cast.');
                }
                if ($this->has('allow_multiple') || $this->has('require_ranked') || $this->has('allow_add_options')) {
                    $validator->errors()->add('allow_multiple', 'Survey modalities cannot be changed after votes have been cast.');
                }

                return;
            }

            if (! $this->has('options')) {
                return;
            }
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
