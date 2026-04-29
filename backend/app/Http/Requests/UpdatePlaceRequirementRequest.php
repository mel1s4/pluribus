<?php

namespace App\Http\Requests;

use App\Models\PlaceRequirement;
use App\Models\Place;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaceRequirementRequest extends FormRequest
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
        /** @var Place|null $place */
        $place = $this->route('place');
        $placeId = (int) ($place?->id ?? 0);
        /** @var PlaceRequirement|null $requirement */
        $requirement = $this->route('requirement');

        return [
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('place_requirements', 'sku')
                    ->where('place_id', $placeId)
                    ->ignore((int) ($requirement?->id ?? 0)),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.9999'],
            'unit' => ['sometimes', 'required', 'string', 'max:64'],
            'recurrence_mode' => ['sometimes', 'required', 'string', Rule::in(PlaceRequirement::RECURRENCE_MODES)],
            'recurrence_weekdays' => ['nullable', 'array'],
            'recurrence_weekdays.*' => ['string', Rule::in(PlaceRequirement::WEEKDAY_KEYS)],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
            'gallery' => ['nullable', 'array', 'max:20'],
            'gallery.*' => ['file', 'image', 'max:5120'],
            'remove_gallery_indices' => ['nullable', 'array'],
            'remove_gallery_indices.*' => ['integer', 'min:0'],
            'example_place_offer_id' => ['sometimes', 'nullable', 'integer', 'exists:place_offers,id'],
            'visibility_scope' => ['sometimes', 'required', 'string', Rule::in([PlaceRequirement::VISIBILITY_SCOPE_PUBLIC, PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE])],
            'audience_ids' => ['nullable', 'array'],
            'audience_ids.*' => ['integer', Rule::exists('place_audiences', 'id')->where('place_id', $placeId)],
        ];
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
        if ($this->has('recurrence_weekdays') && is_string($this->input('recurrence_weekdays'))) {
            $raw = trim($this->input('recurrence_weekdays'));
            if ($raw === '' || strcasecmp($raw, 'null') === 0) {
                $this->merge(['recurrence_weekdays' => []]);
            } else {
                $decoded = json_decode($raw, true);
                $this->merge(['recurrence_weekdays' => is_array($decoded) ? $decoded : []]);
            }
        }
        if ($this->has('example_place_offer_id')) {
            $raw = $this->input('example_place_offer_id');
            if ($raw === '' || $raw === null || (is_string($raw) && strcasecmp($raw, 'null') === 0)) {
                $this->merge(['example_place_offer_id' => null]);
            }
        }
        if ($this->has('audience_ids') && is_string($this->input('audience_ids'))) {
            $raw = trim($this->input('audience_ids'));
            if ($raw === '' || strcasecmp($raw, 'null') === 0) {
                $this->merge(['audience_ids' => []]);
            } else {
                $decoded = json_decode($raw, true);
                $this->merge(['audience_ids' => is_array($decoded) ? $decoded : []]);
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var PlaceRequirement|null $existing */
            $existing = $this->route('requirement');
            $mode = $this->has('recurrence_mode')
                ? $this->input('recurrence_mode')
                : ($existing?->recurrence_mode ?? PlaceRequirement::RECURRENCE_ONCE);
            if ($mode === PlaceRequirement::RECURRENCE_WEEKLY) {
                $wd = $this->has('recurrence_weekdays')
                    ? $this->input('recurrence_weekdays', [])
                    : ($existing?->recurrence_weekdays ?? []);
                if (! is_array($wd) || $wd === []) {
                    $v->errors()->add(
                        'recurrence_weekdays',
                        'Select at least one weekday for a weekly requirement.',
                    );
                }
            }

            $scope = $this->has('visibility_scope')
                ? $this->input('visibility_scope')
                : ($existing?->visibility_scope ?? PlaceRequirement::VISIBILITY_SCOPE_PUBLIC);
            if ($scope !== PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE) {
                return;
            }
            $ids = $this->has('audience_ids')
                ? $this->input('audience_ids', [])
                : ($existing?->audiences()->pluck('place_audiences.id')->all() ?? []);
            if (! is_array($ids) || $ids === []) {
                $v->errors()->add('audience_ids', 'Select at least one audience when visibility is audience-scoped.');
            }
        });
    }
}
