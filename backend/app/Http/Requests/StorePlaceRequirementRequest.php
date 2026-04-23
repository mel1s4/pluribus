<?php

namespace App\Http\Requests;

use App\Models\PlaceRequirement;
use App\Models\Place;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlaceRequirementRequest extends FormRequest
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

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999999.9999'],
            'unit' => ['required', 'string', 'max:64'],
            'recurrence_mode' => ['required', 'string', Rule::in(PlaceRequirement::RECURRENCE_MODES)],
            'recurrence_weekdays' => ['nullable', 'array'],
            'recurrence_weekdays.*' => ['string', Rule::in(PlaceRequirement::WEEKDAY_KEYS)],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
            'gallery' => ['nullable', 'array', 'max:20'],
            'gallery.*' => ['file', 'image', 'max:5120'],
            'example_place_offer_id' => ['nullable', 'integer', 'exists:place_offers,id'],
            'visibility_scope' => ['required', 'string', Rule::in([PlaceRequirement::VISIBILITY_SCOPE_PUBLIC, PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE])],
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
        if (! $this->has('visibility_scope')) {
            $this->merge(['visibility_scope' => PlaceRequirement::VISIBILITY_SCOPE_PUBLIC]);
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
            if ($this->input('recurrence_mode') !== PlaceRequirement::RECURRENCE_WEEKLY) {
                // continue visibility checks below
            }
            if ($this->input('recurrence_mode') === PlaceRequirement::RECURRENCE_WEEKLY) {
                $wd = $this->input('recurrence_weekdays', []);
                if (! is_array($wd) || $wd === []) {
                    $v->errors()->add(
                        'recurrence_weekdays',
                        'Select at least one weekday for a weekly requirement.',
                    );
                }
            }
            if ($this->input('visibility_scope') === PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE) {
                $ids = $this->input('audience_ids', []);
                if (! is_array($ids) || $ids === []) {
                    $v->errors()->add('audience_ids', 'Select at least one audience when visibility is audience-scoped.');
                }
            }
        });
    }
}
