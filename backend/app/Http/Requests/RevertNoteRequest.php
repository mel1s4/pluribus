<?php

namespace App\Http\Requests;

use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevertNoteRequest extends FormRequest
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
        /** @var Note $note */
        $note = $this->route('note');

        return [
            'revision_id' => [
                'required',
                'integer',
                Rule::exists('note_revisions', 'id')->where('note_id', $note->id),
            ],
            'editor_session_id' => ['required', 'uuid'],
        ];
    }
}
