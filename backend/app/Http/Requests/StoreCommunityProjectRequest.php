<?php

namespace App\Http\Requests;

use App\Models\Community;
use App\Models\CommunityProject;
use App\Models\Place;
use App\Support\CommunityProjectGeoValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCommunityProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $slug = $this->route('slug');
        if (! is_string($slug) || $slug === '') {
            return false;
        }
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null) {
            return false;
        }

        return $this->user()->can('create', [CommunityProject::class, $community]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'thesis_title' => ['required', 'string', 'max:500'],
            'thesis_body' => ['nullable', 'string', 'max:50000'],
        ], CommunityProjectGeoValidator::validationRules(false), CommunityProjectGeoValidator::budgetValidationRules(false));
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (! $this->has('location_type')) {
            $merge['location_type'] = Place::LOCATION_NONE;
        }
        if (! $this->has('service_area_type')) {
            $merge['service_area_type'] = Place::SERVICE_AREA_NONE;
        }
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
        CommunityProjectGeoValidator::validateMergedState($validator, [
            'latitude' => $this->input('latitude'),
            'longitude' => $this->input('longitude'),
            'location_type' => (string) $this->input('location_type', Place::LOCATION_NONE),
            'service_area_type' => (string) $this->input('service_area_type', Place::SERVICE_AREA_NONE),
            'radius_meters' => $this->input('radius_meters'),
            'area_geojson' => $this->input('area_geojson'),
        ]);
    }
}
