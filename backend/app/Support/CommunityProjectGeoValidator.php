<?php

namespace App\Support;

use App\Models\CommunityProject;
use App\Models\Place;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CommunityProjectGeoValidator
{
    /**
     * @return array<string, mixed>
     */
    public static function validationRules(bool $forPatch = false): array
    {
        $p = $forPatch ? ['sometimes'] : [];

        return [
            'latitude' => array_merge($p, ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude']),
            'longitude' => array_merge($p, ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude']),
            'location_type' => array_merge($p, ['nullable', 'string', Rule::in(Place::LOCATION_TYPES)]),
            'service_area_type' => array_merge($p, ['nullable', 'string', Rule::in(Place::SERVICE_AREA_TYPES)]),
            'radius_meters' => array_merge($p, ['nullable', 'integer', 'min:1', 'max:500000']),
            'area_geojson' => array_merge($p, ['nullable', 'array']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function projectFieldRules(bool $forPatch = false): array
    {
        $p = $forPatch ? ['sometimes'] : [];
        $statusRule = $forPatch
            ? ['sometimes', 'string', Rule::in(CommunityProject::STATUSES)]
            : ['sometimes', 'string', Rule::in(CommunityProject::STATUSES)];

        return [
            'status' => $statusRule,
            'deadline' => array_merge($p, ['nullable', 'date']),
            'has_budget' => array_merge($p, ['sometimes', 'boolean']),
            'has_job_positions' => array_merge($p, ['sometimes', 'boolean']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function budgetValidationRules(bool $forPatch = false): array
    {
        $p = $forPatch ? ['sometimes'] : [];

        return [
            'budget_items' => array_merge($p, ['nullable', 'array', 'max:100']),
            'budget_items.*.name' => ['required', 'string', 'max:255'],
            'budget_items.*.description' => ['nullable', 'string', 'max:10000'],
            'budget_items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'budget_items.*.units' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'budget_items.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function jobPositionValidationRules(bool $forPatch = false): array
    {
        $p = $forPatch ? ['sometimes'] : [];

        return [
            'job_positions' => array_merge($p, ['nullable', 'array', 'max:50']),
            'job_positions.*.title' => ['required', 'string', 'max:255'],
            'job_positions.*.tasks' => ['nullable', 'array', 'max:100'],
            'job_positions.*.tasks.*.body' => ['required', 'string', 'max:5000'],
            'job_positions.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    /**
     * @param  array{
     *   latitude: mixed,
     *   longitude: mixed,
     *   location_type: string,
     *   service_area_type: string,
     *   radius_meters: mixed,
     *   area_geojson: mixed
     * }  $s
     */
    public static function validateMergedState(Validator $v, array $s): void
    {
        $v->after(function (Validator $inner) use ($s): void {
            $lat = $s['latitude'];
            $lng = $s['longitude'];
            $lt = $s['location_type'];
            $sat = $s['service_area_type'];
            $radius = $s['radius_meters'];
            $geo = $s['area_geojson'];

            if ($lt === Place::LOCATION_POINT) {
                if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                    $inner->errors()->add('latitude', __('Latitude and longitude are required when location is a point on the map.'));
                }
            }

            if ($sat === Place::SERVICE_AREA_RADIUS || $sat === Place::SERVICE_AREA_POLYGON) {
                if ($lt === Place::LOCATION_NONE) {
                    $inner->errors()->add('location_type', __('Location cannot be “none” when the service area is radius or polygon.'));
                }
                if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                    $inner->errors()->add('latitude', __('Latitude and longitude are required when the service area is radius or polygon.'));
                }
            }

            if ($sat === Place::SERVICE_AREA_RADIUS) {
                if ($radius === null || $radius === '') {
                    $inner->errors()->add('radius_meters', __('Radius in meters is required when service area is radius.'));
                }
            }
            if ($sat === Place::SERVICE_AREA_POLYGON) {
                if (! self::isValidPolygonGeoJson($geo)) {
                    $inner->errors()->add('area_geojson', __('A valid GeoJSON Polygon is required when service area is polygon.'));
                }
            }
            if ($sat === Place::SERVICE_AREA_NONE) {
                if ($radius !== null && $radius !== '') {
                    $inner->errors()->add('radius_meters', __('Must be empty when service area is none.'));
                }
                if (is_array($geo) && $geo !== []) {
                    $inner->errors()->add('area_geojson', __('Must be empty when service area is none.'));
                }
            }

            if ($lt === Place::LOCATION_NONE && $sat === Place::SERVICE_AREA_NONE) {
                if (($lat !== null && $lat !== '') || ($lng !== null && $lng !== '')) {
                    $inner->errors()->add('latitude', __('Latitude and longitude must be empty when both location and service area are none.'));
                }
            }
        });
    }

    public static function validateNestedSections(Validator $v): void
    {
        $v->after(function (Validator $inner): void {
            if ($inner->errors()->isNotEmpty()) {
                return;
            }

            $hasBudget = filter_var($inner->getData()['has_budget'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $budgetItems = $inner->getData()['budget_items'] ?? null;
            if ($hasBudget) {
                if (! is_array($budgetItems) || $budgetItems === []) {
                    $inner->errors()->add('budget_items', __('At least one budget line is required when the project has a budget.'));
                }
            }

            $hasJobPositions = filter_var($inner->getData()['has_job_positions'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $jobPositions = $inner->getData()['job_positions'] ?? null;
            if ($hasJobPositions) {
                if (! is_array($jobPositions) || $jobPositions === []) {
                    $inner->errors()->add('job_positions', __('At least one job position is required when the project has job positions.'));
                }
            }
        });
    }

    /**
     * @param  mixed  $value
     */
    public static function isValidPolygonGeoJson($value): bool
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
