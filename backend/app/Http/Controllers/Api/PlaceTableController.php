<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceTableRequest;
use App\Http\Requests\UpdatePlaceTableRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TableResource;
use App\Models\Order;
use App\Models\Place;
use App\Models\Table;
use App\Models\TableSeating;
use Illuminate\Http\JsonResponse;

class PlaceTableController extends Controller
{
    public function show(Place $place, Table $table): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $table->place_id !== (int) $place->id) {
            abort(404);
        }
        $seatings = TableSeating::query()
            ->where('table_id', $table->id)
            ->with('user:id,name')
            ->orderByDesc('last_seen_at')
            ->limit(80)
            ->get()
            ->map(static function (TableSeating $s): array {
                return [
                    'id' => $s->id,
                    'last_seen_at' => $s->last_seen_at?->toIso8601String(),
                    'user' => $s->user !== null ? [
                        'id' => $s->user->id,
                        'name' => (string) $s->user->name,
                    ] : null,
                ];
            });

        $orders = Order::query()
            ->whereHas('items', fn ($q) => $q->where('place_id', $place->id)->where('table_id', $table->id))
            ->with([
                'user:id,name',
                'items' => fn ($q) => $q->where('place_id', $place->id)
                    ->where('table_id', $table->id)
                    ->with(['place', 'table', 'offer']),
            ])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        foreach ($orders as $order) {
            $sub = '0.00';
            foreach ($order->items as $item) {
                $sub = number_format((float) $sub + (float) $item->subtotal, 2, '.', '');
            }
            $order->setAttribute('place_subtotal', $sub);
        }

        return response()->json([
            'table' => new TableResource($table),
            'seatings' => $seatings,
            'orders' => OrderResource::collection($orders)->resolve(),
        ]);
    }

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
