<?php

namespace App\Services\PlaceCsv;

use App\Models\Place;
use App\Models\PlaceAudience;
use App\Models\PlaceOffer;
use App\Support\CsvImportResult;
use App\Support\PlaceSku;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlaceOfferCsvService
{
    /** @var list<string> */
    public const HEADERS = ['sku', 'title', 'description', 'price', 'visibility_scope', 'audience_keys', 'tags'];

    /**
     * @return list<array<string, string>>
     */
    public function exportRows(Place $place): array
    {
        return $place->offers()
            ->with('audiences:id,name')
            ->orderBy('id')
            ->get()
            ->map(function (PlaceOffer $offer): array {
                return [
                    'sku' => (string) $offer->sku,
                    'title' => (string) $offer->title,
                    'description' => (string) ($offer->description ?? ''),
                    'price' => (string) $offer->price,
                    'visibility_scope' => (string) $offer->visibility_scope,
                    'audience_keys' => $offer->audiences->pluck('name')->implode(','),
                    'tags' => implode(',', $offer->tags ?? []),
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
            $result->addRowError(1, null, 'header', 'CSV header does not match the expected offer format.');

            return $result;
        }

        $audiencesByName = PlaceAudience::query()
            ->where('place_id', $place->id)
            ->get()
            ->mapWithKeys(fn (PlaceAudience $aud) => [mb_strtolower(trim($aud->name)) => (int) $aud->id]);

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

            $data = [
                'sku' => $sku,
                'title' => trim($assoc['title'] ?? ''),
                'description' => trim($assoc['description'] ?? '') ?: null,
                'price' => trim($assoc['price'] ?? ''),
                'visibility_scope' => trim($assoc['visibility_scope'] ?? PlaceOffer::VISIBILITY_SCOPE_PUBLIC),
                'audience_ids' => $audienceIds,
                'tags' => $this->splitList($assoc['tags'] ?? ''),
            ];

            $validator = Validator::make($data, [
                'sku' => ['required', 'string', 'max:64'],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:10000'],
                'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
                'visibility_scope' => ['required', Rule::in([PlaceOffer::VISIBILITY_SCOPE_PUBLIC, PlaceOffer::VISIBILITY_SCOPE_AUDIENCE])],
                'audience_ids' => ['array'],
                'tags' => ['array', 'max:50'],
                'tags.*' => ['string', 'max:64'],
            ]);
            $validator->after(function ($v) use ($data): void {
                if ($data['visibility_scope'] === PlaceOffer::VISIBILITY_SCOPE_AUDIENCE && $data['audience_ids'] === []) {
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

            $offer = PlaceOffer::query()->firstOrNew([
                'place_id' => $place->id,
                'sku' => $data['sku'],
            ]);
            $isNew = ! $offer->exists;
            $offer->fill([
                'title' => $data['title'],
                'description' => $data['description'],
                'price' => $data['price'],
                'tags' => $data['tags'] === [] ? null : $data['tags'],
                'visibility_scope' => $data['visibility_scope'],
            ]);
            $offer->save();
            $offer->audiences()->sync($data['visibility_scope'] === PlaceOffer::VISIBILITY_SCOPE_AUDIENCE ? $audienceIds : []);
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
