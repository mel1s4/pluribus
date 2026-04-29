<?php

namespace App\Http\Requests;

use App\Support\PlaceBrandLinks;
use App\Support\PlaceServiceScheduleNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlaceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('places', 'slug'),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
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
        if ($this->has('tags') && is_string($this->input('tags'))) {
            $raw = trim($this->input('tags'));
            if ($raw === '' || strcasecmp($raw, 'null') === 0) {
                $this->merge(['tags' => []]);
            } else {
                $decoded = json_decode($raw, true);
                $this->merge(['tags' => is_array($decoded) ? $decoded : []]);
            }
        }
        if ($this->has('is_public')) {
            $raw = $this->input('is_public');
            if (is_string($raw)) {
                $lower = strtolower(trim($raw));
                if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
                    $this->merge(['is_public' => true]);
                } elseif (in_array($lower, ['0', 'false', 'no', 'off', ''], true)) {
                    $this->merge(['is_public' => false]);
                }
            }
        }
        if ($this->has('service_schedule')) {
            $raw = $this->input('service_schedule');
            if (is_string($raw)) {
                $trim = trim($raw);
                if ($trim === '' || strcasecmp($trim, 'null') === 0) {
                    $this->merge(['service_schedule' => PlaceServiceScheduleNormalizer::normalize([])]);
                } else {
                    $decoded = json_decode($trim, true);
                    $this->merge(['service_schedule' => PlaceServiceScheduleNormalizer::normalize(is_array($decoded) ? $decoded : [])]);
                }
            } else {
                $this->merge(['service_schedule' => PlaceServiceScheduleNormalizer::normalize($raw)]);
            }
        }
        if ($this->has('brand_links')) {
            $raw = $this->input('brand_links');
            if (is_string($raw)) {
                $trim = trim($raw);
                if ($trim === '' || strcasecmp($trim, 'null') === 0) {
                    $this->merge(['brand_links' => []]);
                } else {
                    $decoded = json_decode($trim, true);
                    $this->merge(['brand_links' => is_array($decoded) ? $decoded : []]);
                }
            }
        }
    }
}
