<?php

namespace App\Http\Requests;

use App\Support\PlaceServiceScheduleNormalizer;
use Illuminate\Foundation\Http\FormRequest;

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
            'description' => ['nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
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
    }
}
