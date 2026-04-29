<?php

namespace App\Http\Requests;

use App\Models\Place;
use App\Support\PlaceBrandLinks;
use App\Support\PlaceServiceScheduleNormalizer;
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
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('places', 'slug')->ignore($this->route('place')),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'location_type' => ['sometimes', 'required', 'string', Rule::in(Place::LOCATION_TYPES)],
            'service_area_type' => ['sometimes', 'required', 'string', Rule::in(Place::SERVICE_AREA_TYPES)],
            'radius_meters' => ['nullable', 'integer', 'min:1', 'max:500000'],
            'area_geojson' => ['nullable', 'array'],
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
            'remove_logo' => ['sometimes', 'boolean'],
            'logo_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_public' => ['sometimes', 'boolean'],
            'brand_links' => ['nullable', 'array', 'max:'.PlaceBrandLinks::MAX_LINKS],
            'brand_links.*.title' => ['required_with:brand_links.*.url,brand_links.*.icon', 'string', 'max:80'],
            'brand_links.*.url' => ['required_with:brand_links.*.title,brand_links.*.icon', 'url', 'max:2048'],
            'brand_links.*.icon' => ['required_with:brand_links.*.title,brand_links.*.url', 'string', Rule::in(PlaceBrandLinks::ICON_KEYS)],
        ];

        $time = ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'];
        foreach (PlaceServiceScheduleNormalizer::DAY_KEYS as $day) {
            $rules['service_schedule.'.$day] = ['nullable', 'array', 'max:'.PlaceServiceScheduleNormalizer::MAX_SLOTS_PER_DAY];
            $rules['service_schedule.'.$day.'.*.open'] = $time;
            $rules['service_schedule.'.$day.'.*.close'] = $time;
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('is_public')) {
            $raw = $this->input('is_public');
            if (is_string($raw)) {
                $lower = strtolower(trim($raw));
                if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
                    $merge['is_public'] = true;
                } elseif (in_array($lower, ['0', 'false', 'no', 'off', ''], true)) {
                    $merge['is_public'] = false;
                }
            }
        }
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
        if ($this->has('logo_background_color') && trim((string) $this->input('logo_background_color')) === '') {
            $merge['logo_background_color'] = null;
        }
        if ($this->has('service_schedule')) {
            $raw = $this->input('service_schedule');
            if (is_string($raw)) {
                $trim = trim($raw);
                if ($trim === '' || strcasecmp($trim, 'null') === 0) {
                    $merge['service_schedule'] = PlaceServiceScheduleNormalizer::normalize([]);
                } else {
                    $decoded = json_decode($raw, true);
                    $merge['service_schedule'] = PlaceServiceScheduleNormalizer::normalize(is_array($decoded) ? $decoded : []);
                }
            } else {
                $merge['service_schedule'] = PlaceServiceScheduleNormalizer::normalize($raw);
            }
        }
        if ($this->has('brand_links')) {
            $raw = $this->input('brand_links');
            if (is_string($raw)) {
                $trim = trim($raw);
                if ($trim === '' || strcasecmp($trim, 'null') === 0) {
                    $merge['brand_links'] = [];
                } else {
                    $decoded = json_decode($trim, true);
                    $merge['brand_links'] = is_array($decoded) ? $decoded : [];
                }
            }
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
            $lt = $this->input('location_type', $place->location_type ?? Place::LOCATION_NONE);
            $radius = $this->has('radius_meters')
                ? $this->input('radius_meters')
                : $place->radius_meters;
            $geo = $this->has('area_geojson')
                ? $this->input('area_geojson')
                : $place->area_geojson;
            $lat = $this->has('latitude') ? $this->input('latitude') : $place->latitude;
            $lng = $this->has('longitude') ? $this->input('longitude') : $place->longitude;

            if ($lt === Place::LOCATION_POINT) {
                if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                    $v->errors()->add('latitude', 'Latitude and longitude are required when location is a point on the map.');
                }
            }

            if ($sat === Place::SERVICE_AREA_RADIUS || $sat === Place::SERVICE_AREA_POLYGON) {
                if ($this->has('location_type') && $this->input('location_type') === Place::LOCATION_NONE) {
                    $v->errors()->add('location_type', 'Location cannot be “none” when the service area is radius or polygon.');
                }
                if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                    $v->errors()->add('latitude', 'Latitude and longitude are required when the service area is radius or polygon.');
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

            if ($lt === Place::LOCATION_NONE && $sat === Place::SERVICE_AREA_NONE) {
                if (($lat !== null && $lat !== '') || ($lng !== null && $lng !== '')) {
                    $v->errors()->add('latitude', 'Latitude and longitude must be empty when both location and service area are none.');
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
