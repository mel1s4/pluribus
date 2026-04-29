<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceTableRequest;
use App\Http\Requests\UpdatePlaceTableRequest;
use App\Http\Resources\TableResource;
use App\Models\Place;
use App\Models\Table;
use Illuminate\Http\JsonResponse;

class PlaceTableController extends Controller
{
    public function index(Place $place): JsonResponse
    {
        $this->authorize('view', $place);

        return response()->json([
            'data' => TableResource::collection(
                $place->tables()->orderBy('name')->get()
            ),
        ]);
    }

    public function store(StorePlaceTableRequest $request, Place $place): JsonResponse
    {
        $this->authorize('update', $place);
        $validated = $request->validated();
        $table = $place->tables()->create(['name' => $validated['name']]);

        return response()->json(['table' => new TableResource($table)], 201);
    }

    public function update(UpdatePlaceTableRequest $request, Place $place, Table $table): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $table->place_id !== (int) $place->id) {
            abort(404);
        }
        $table->update(['name' => $request->validated('name')]);

        return response()->json(['table' => new TableResource($table->fresh())]);
    }

    public function destroy(Place $place, Table $table): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $table->place_id !== (int) $place->id) {
            abort(404);
        }
        $table->delete();

        return response()->json(null, 204);
    }
}
