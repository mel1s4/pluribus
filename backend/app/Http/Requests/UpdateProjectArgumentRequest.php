<?php

namespace App\Http\Requests;

use App\Models\ProjectArgument;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectArgumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectArgument|null $argument */
        $argument = $this->route('argument');
        if (! $argument instanceof ProjectArgument) {
            return false;
        }

        return $this->user()->can('update', $argument);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stance' => ['sometimes', 'string', 'in:'.ProjectArgument::STANCE_PRO.','.ProjectArgument::STANCE_CON],
            'title' => ['sometimes', 'string', 'max:500'],
            'body' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ];
    }
}
