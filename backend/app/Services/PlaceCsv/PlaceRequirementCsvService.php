<?php

namespace App\Services\PlaceCsv;

use App\Models\Place;
use App\Models\PlaceAudience;
use App\Models\PlaceOffer;
use App\Models\PlaceRequirement;
use App\Support\CsvImportResult;
use App\Support\PlaceSku;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlaceRequirementCsvService
{
    /** @var list<string> */
    public const HEADERS = [
        'sku',
        'title',
        'description',
        'quantity',
        'unit',
        'recurrence_mode',
        'recurrence_weekdays',
        'visibility_scope',
        'audience_keys',
        'tags',
        'example_offer_sku',
    ];

    /**
     * @return list<array<string, string>>
     */
    public function exportRows(Place $place): array
    {
        return $place->requirements()
            ->with(['audiences:id,name', 'exampleOffer:id,sku'])
            ->orderBy('id')
            ->get()
            ->map(function (PlaceRequirement $row): array {
                return [
                    'sku' => (string) $row->sku,
                    'title' => (string) $row->title,
                    'description' => (string) ($row->description ?? ''),
                    'quantity' => (string) $row->quantity,
                    'unit' => (string) $row->unit,
                    'recurrence_mode' => (string) $row->recurrence_mode,
                    'recurrence_weekdays' => implode(',', $row->recurrence_weekdays ?? []),
                    'visibility_scope' => (string) $row->visibility_scope,
                    'audience_keys' => $row->audiences->pluck('name')->implode(','),
                    'tags' => implode(',', $row->tags ?? []),
                    'example_offer_sku' => (string) ($row->exampleOffer?->sku ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    public function import(Place $place, UploadedFile $file): CsvImportResult
    {
        $result = new CsvImportResult();
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            $result->addRowError(0, null, 'file', 'Unable to read CSV file.');
            return $result;
        }

        $header = fgetcsv($handle);
        if (is_array($header) && isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
        }
        if (! is_array($header) || array_map('trim', $header) !== self::HEADERS) {
            fclose($handle);
            $result->addRowError(1, null, 'header', 'CSV header does not match the expected requirement format.');
            return $result;
        }

        $audiencesByName = PlaceAudience::query()
            ->where('place_id', $place->id)
            ->get()
            ->mapWithKeys(fn (PlaceAudience $aud) => [mb_strtolower(trim($aud->name)) => (int) $aud->id]);

        $offersBySku = PlaceOffer::query()
            ->where('place_id', $place->id)
            ->pluck('id', 'sku');

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (! is_array($row)) {
                continue;
            }
            $result->totalRows++;
            $assoc = array_combine(self::HEADERS, array_pad($row, count(self::HEADERS), ''));
            if (! is_array($assoc)) {
                $result->addRowError($rowNumber, null, 'row', 'Unable to parse row.');
                continue;
            }

            $sku = PlaceSku::normalize($assoc['sku'] ?? '');
            $audienceNames = $this->splitList($assoc['audience_keys'] ?? '');
            $audienceIds = [];
            foreach ($audienceNames as $name) {
                $key = mb_strtolower(trim($name));
                if (! isset($audiencesByName[$key])) {
                    $result->addRowError($rowNumber, $sku, 'audience_keys', 'Unknown audience: '.$name);
                    continue 2;
                }
                $audienceIds[] = (int) $audiencesByName[$key];
            }

            $exampleSku = PlaceSku::normalize($assoc['example_offer_sku'] ?? '');
            $exampleOfferId = null;
            if ($exampleSku !== '') {
                $exampleOfferId = isset($offersBySku[$exampleSku]) ? (int) $offersBySku[$exampleSku] : null;
                if ($exampleOfferId === null) {
                    $result->addRowError($rowNumber, $sku, 'example_offer_sku', 'Unknown offer SKU: '.$exampleSku);
                    continue;
                }
            }

            $data = [
                'sku' => $sku,
                'title' => trim($assoc['title'] ?? ''),
                'description' => trim($assoc['description'] ?? '') ?: null,
                'quantity' => trim($assoc['quantity'] ?? ''),
                'unit' => trim($assoc['unit'] ?? ''),
                'recurrence_mode' => trim($assoc['recurrence_mode'] ?? PlaceRequirement::RECURRENCE_ONCE),
                'recurrence_weekdays' => $this->splitList($assoc['recurrence_weekdays'] ?? ''),
                'visibility_scope' => trim($assoc['visibility_scope'] ?? PlaceRequirement::VISIBILITY_SCOPE_PUBLIC),
                'audience_ids' => $audienceIds,
                'tags' => $this->splitList($assoc['tags'] ?? ''),
                'example_place_offer_id' => $exampleOfferId,
            ];

            $validator = Validator::make($data, [
                'sku' => ['required', 'string', 'max:64'],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:10000'],
                'quantity' => ['required', 'numeric', 'min:0', 'max:9999999999.9999'],
                'unit' => ['required', 'string', 'max:64'],
                'recurrence_mode' => ['required', Rule::in(PlaceRequirement::RECURRENCE_MODES)],
                'recurrence_weekdays' => ['array'],
                'recurrence_weekdays.*' => ['string', Rule::in(PlaceRequirement::WEEKDAY_KEYS)],
                'visibility_scope' => ['required', Rule::in([PlaceRequirement::VISIBILITY_SCOPE_PUBLIC, PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE])],
                'audience_ids' => ['array'],
                'tags' => ['array', 'max:50'],
                'tags.*' => ['string', 'max:64'],
                'example_place_offer_id' => ['nullable', 'integer'],
            ]);
            $validator->after(function ($v) use ($data): void {
                if ($data['recurrence_mode'] === PlaceRequirement::RECURRENCE_WEEKLY && $data['recurrence_weekdays'] === []) {
                    $v->errors()->add('recurrence_weekdays', 'Select at least one weekday for a weekly requirement.');
                }
                if ($data['visibility_scope'] === PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE && $data['audience_ids'] === []) {
                    $v->errors()->add('audience_ids', 'Select at least one audience when visibility is audience-scoped.');
                }
            });
            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $field => $messages) {
                    foreach ($messages as $message) {
                        $result->addRowError($rowNumber, $sku, (string) $field, (string) $message);
                    }
                }
                continue;
            }

            $requirement = PlaceRequirement::query()->firstOrNew([
                'place_id' => $place->id,
                'sku' => $data['sku'],
            ]);
            $isNew = ! $requirement->exists;
            $requirement->fill([
                'title' => $data['title'],
                'description' => $data['description'],
                'quantity' => $data['quantity'],
                'unit' => $data['unit'],
                'recurrence_mode' => $data['recurrence_mode'],
                'recurrence_weekdays' => $data['recurrence_mode'] === PlaceRequirement::RECURRENCE_WEEKLY ? $data['recurrence_weekdays'] : null,
                'tags' => $data['tags'] === [] ? null : $data['tags'],
                'example_place_offer_id' => $data['example_place_offer_id'],
                'visibility_scope' => $data['visibility_scope'],
            ]);
            $requirement->save();
            $requirement->audiences()->sync($data['visibility_scope'] === PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE ? $audienceIds : []);
            $isNew ? $result->created++ : $result->updated++;
        }

        fclose($handle);
        return $result;
    }

    /**
     * @return list<string>
     */
    private function splitList(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, static fn (string $item): bool => $item !== '');

        return array_values($parts);
    }
}
