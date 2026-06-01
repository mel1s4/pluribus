<?php

namespace App\Http\Requests;

use App\Models\CommunityProject;
use App\Models\Place;
use App\Support\CommunityProjectGeoValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCommunityProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CommunityProject|null $project */
        $project = $this->route('project');
        if (! $project instanceof CommunityProject) {
            return false;
        }

        return $this->user()->can('update', $project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ], CommunityProjectGeoValidator::projectFieldRules(true), CommunityProjectGeoValidator::validationRules(true), CommunityProjectGeoValidator::budgetValidationRules(true), CommunityProjectGeoValidator::jobPositionValidationRules(true));
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('area_geojson') && is_string($this->input('area_geojson'))) {
            $raw = trim((string) $this->input('area_geojson'));
            if ($raw === '' || strcasecmp($raw, 'null') === 0) {
                $merge['area_geojson'] = null;
            } else {
                $decoded = json_decode($raw, true);
                $merge['area_geojson'] = is_array($decoded) ? $decoded : null;
            }
        }
        if ($this->has('radius_meters') && $this->input('radius_meters') === '') {
            $merge['radius_meters'] = null;
        }
        if ($this->has('deadline') && $this->input('deadline') === '') {
            $merge['deadline'] = null;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function withValidator(Validator $validator): void
    {
        /** @var CommunityProject|null $project */
        $project = $this->route('project');
        if (! $project instanceof CommunityProject) {
            return;
        }

        CommunityProjectGeoValidator::validateMergedState($validator, [
            'latitude' => $this->has('latitude') ? $this->input('latitude') : $project->latitude,
            'longitude' => $this->has('longitude') ? $this->input('longitude') : $project->longitude,
            'location_type' => $this->has('location_type')
                ? (string) $this->input('location_type')
                : (string) ($project->location_type ?? Place::LOCATION_NONE),
            'service_area_type' => $this->has('service_area_type')
                ? (string) $this->input('service_area_type')
                : (string) ($project->service_area_type ?? Place::SERVICE_AREA_NONE),
            'radius_meters' => $this->has('radius_meters') ? $this->input('radius_meters') : $project->radius_meters,
            'area_geojson' => $this->has('area_geojson') ? $this->input('area_geojson') : $project->area_geojson,
        ]);

        $validator->after(function (Validator $inner) use ($project): void {
            if ($inner->errors()->isNotEmpty()) {
                return;
            }

            $hasBudget = $this->has('has_budget')
                ? filter_var($this->input('has_budget'), FILTER_VALIDATE_BOOLEAN)
                : (bool) $project->has_budget;
            $budgetItems = $this->has('budget_items') ? $this->input('budget_items') : null;
            if ($hasBudget) {
                if ($this->has('budget_items')) {
                    if (! is_array($budgetItems) || $budgetItems === []) {
                        $inner->errors()->add('budget_items', __('At least one budget line is required when the project has a budget.'));
                    }
                } elseif (! $project->budgetItems()->exists()) {
                    $inner->errors()->add('budget_items', __('At least one budget line is required when the project has a budget.'));
                }
            }

            $hasJobPositions = $this->has('has_job_positions')
                ? filter_var($this->input('has_job_positions'), FILTER_VALIDATE_BOOLEAN)
                : (bool) $project->has_job_positions;
            $jobPositions = $this->has('job_positions') ? $this->input('job_positions') : null;
            if ($hasJobPositions) {
                if ($this->has('job_positions')) {
                    if (! is_array($jobPositions) || $jobPositions === []) {
                        $inner->errors()->add('job_positions', __('At least one job position is required when the project has job positions.'));
                    }
                } elseif (! $project->jobPositions()->exists()) {
                    $inner->errors()->add('job_positions', __('At least one job position is required when the project has job positions.'));
                }
            }
        });
    }
}
