<?php

namespace App\Http\Requests;

use App\Models\CommunityProject;
use App\Models\ProjectArgument;
use App\Support\ProjectArgumentDepth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectArgumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CommunityProject|null $project */
        $project = $this->route('project');
        if (! $project instanceof CommunityProject) {
            return false;
        }

        return $this->user()->can('create', [ProjectArgument::class, $project]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CommunityProject $project */
        $project = $this->route('project');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('project_arguments', 'id')->where('project_id', $project->id),
            ],
            'stance' => ['required', 'string', 'in:'.ProjectArgument::STANCE_PRO.','.ProjectArgument::STANCE_CON],
            'title' => ['required', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:50000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            /** @var CommunityProject $project */
            $project = $this->route('project');
            $parentId = $this->input('parent_id');
            $parentId = $parentId !== null && $parentId !== '' ? (int) $parentId : null;

            $depth = ProjectArgumentDepth::depthForNewChild($parentId, (int) $project->id);
            if ($depth > ProjectArgumentDepth::MAX_DEPTH) {
                $validator->errors()->add('parent_id', __('Arguments cannot nest deeper than :max levels.', ['max' => ProjectArgumentDepth::MAX_DEPTH]));
            }
        });
    }
}
