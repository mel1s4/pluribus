<?php

namespace App\Http\Requests;

use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlaceRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'service_area_type' => ['sometimes', 'required', 'string', Rule::in(Place::SERVICE_AREA_TYPES)],
            'radius_meters' => ['nullable', 'integer', 'min:1', 'max:500000'],
            'area_geojson' => ['nullable', 'array'],
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('tags') && is_string($this->input('tags'))) {
            $raw = trim($this->input('tags'));
            if ($raw === '' || strcasecmp($raw, 'null') === 0) {
                $merge['tags'] = [];
            } else {
                $decoded = json_decode($raw, true);
                $merge['tags'] = is_array($decoded) ? $decoded : [];
            }
        }
        if ($this->has('area_geojson') && is_string($this->input('area_geojson'))) {
            $raw = trim($this->input('area_geojson'));
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
        $validator->after(function (Validator $v): void {
            /** @var Place|null $place */
            $place = $this->route('place');
            if (! $place instanceof Place) {
                return;
            }
            $sat = $this->input('service_area_type', $place->service_area_type);
            $radius = $this->has('radius_meters')
                ? $this->input('radius_meters')
                : $place->radius_meters;
            $geo = $this->has('area_geojson')
                ? $this->input('area_geojson')
                : $place->area_geojson;
            $lat = $this->has('latitude') ? $this->input('latitude') : $place->latitude;
            $lng = $this->has('longitude') ? $this->input('longitude') : $place->longitude;

            if ($sat === Place::SERVICE_AREA_RADIUS || $sat === Place::SERVICE_AREA_POLYGON) {
                if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                    $v->errors()->add('latitude', 'Location (latitude and longitude) is required when the service area is radius or polygon.');
                }
            }

            if ($sat === Place::SERVICE_AREA_RADIUS) {
                if ($radius === null || $radius === '') {
                    $v->errors()->add('radius_meters', 'Radius in meters is required when service area is radius.');
                }
            }
            if ($sat === Place::SERVICE_AREA_POLYGON) {
                if (! $this->isValidPolygonGeoJson($geo)) {
                    $v->errors()->add('area_geojson', 'A valid GeoJSON Polygon is required when service area is polygon.');
                }
            }
            if ($sat === Place::SERVICE_AREA_NONE) {
                if ($radius !== null && $radius !== '') {
                    $v->errors()->add('radius_meters', 'Must be empty when service area is none.');
                }
                if (is_array($geo) && $geo !== []) {
                    $v->errors()->add('area_geojson', 'Must be empty when service area is none.');
                }
            }
        });
    }

    /**
     * @param  mixed  $value
     */
    private function isValidPolygonGeoJson($value): bool
    {
        if (! is_array($value)) {
            return false;
        }
        if (($value['type'] ?? null) !== 'Polygon') {
            return false;
        }
        $coords = $value['coordinates'] ?? null;
        if (! is_array($coords) || $coords === []) {
            return false;
        }
        foreach ($coords as $ring) {
            if (! is_array($ring) || count($ring) < 4) {
                return false;
            }
        }

        return true;
    }
}
