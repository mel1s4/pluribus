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
            'thesis_title' => ['sometimes', 'string', 'max:500'],
            'thesis_body' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'status' => ['sometimes', 'string', 'in:'.CommunityProject::STATUS_OPEN],
        ], CommunityProjectGeoValidator::validationRules(true), CommunityProjectGeoValidator::budgetValidationRules(true));
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
    }
}
