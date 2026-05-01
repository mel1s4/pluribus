<?php

namespace App\Http\Requests;

use App\Models\Chat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatRequest extends FormRequest
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
        $userId = (int) ($this->user()?->id ?? 0);
        $type = $this->input('type');

        $memberIdsRules = ['array'];
        if ($type === Chat::TYPE_GROUP) {
            array_unshift($memberIdsRules, 'required');
            $memberIdsRules[] = 'min:1';
        } elseif ($type === Chat::TYPE_DIRECT) {
            array_unshift($memberIdsRules, 'required');
            $memberIdsRules[] = 'size:1';
        } else {
            $memberIdsRules = ['sometimes', 'array'];
        }

        return [
            'type' => ['required', Rule::in(Chat::TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
            'icon_emoji' => ['nullable', 'string', 'max:16'],
            'icon_bg_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'folder_id' => ['nullable', 'exists:folders,id'],
            'member_ids' => $memberIdsRules,
            'member_ids.*' => ['integer', 'exists:users,id', 'distinct', Rule::notIn([$userId])],
        ];
    }
}
