<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\PlaceCsv\PlaceOfferCsvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlaceOfferCsvController extends Controller
{
    public function export(Request $request, Place $place, PlaceOfferCsvService $service): StreamedResponse
    {
        $this->authorize('view', $place);
        $rows = $service->exportRows($place);
        $filename = 'place-'.$place->id.'-offers.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, PlaceOfferCsvService::HEADERS);
            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn (string $col) => $row[$col] ?? '', PlaceOfferCsvService::HEADERS));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request, Place $place, PlaceOfferCsvService $service): JsonResponse
    {
        $this->authorize('update', $place);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $result = $service->import($place, $validated['file']);

        return response()->json($result->toArray());
    }
}
