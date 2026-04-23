<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class PlaceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $uid = (int) $request->user()->id;
        $places = Place::query()
            ->where(function ($q) use ($uid): void {
                $q->where('user_id', $uid)
                    ->orWhereHas('administrators', fn ($q2) => $q2->where('users.id', $uid));
            })
            ->with(['administrators' => fn ($q) => $q->where('users.id', $uid)])
            ->orderByDesc('id')
            ->get();

        return PlaceResource::collection($places);
    }

    public function store(StorePlaceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        unset($validated['logo']);

        $tags = $validated['tags'] ?? [];
        $tags = is_array($tags) ? array_values(array_filter(array_map('strval', $tags), fn (string $t) => $t !== '')) : [];

        $place = Place::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'tags' => $tags === [] ? null : $tags,
            'latitude' => null,
            'longitude' => null,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('places/'.$place->id, 'public');
            $place->logo_path = $path;
            $place->save();
        }

        return response()->json([
            'place' => new PlaceResource($place->fresh()),
        ], 201);
    }

    public function show(Request $request, Place $place): JsonResponse
    {
        $this->authorize('view', $place);

        $uid = (int) $request->user()->id;
        $place->load([
            'offers',
            'administrators' => fn ($q) => $q->where('users.id', $uid),
        ]);

        return response()->json([
            'place' => new PlaceResource($place),
        ]);
    }

    public function update(UpdatePlaceRequest $request, Place $place): JsonResponse
    {
        $this->authorize('update', $place);

        $validated = $request->validated();
        unset($validated['logo'], $validated['remove_logo']);

        if (array_key_exists('tags', $validated)) {
            $tags = $validated['tags'] ?? [];
            $tags = is_array($tags) ? array_values(array_filter(array_map('strval', $tags), fn (string $t) => $t !== '')) : [];
            $validated['tags'] = $tags === [] ? null : $tags;
        }

        if (array_key_exists('service_area_type', $validated)) {
            if ($validated['service_area_type'] === Place::SERVICE_AREA_NONE) {
                $validated['radius_meters'] = null;
                $validated['area_geojson'] = null;
            }
            if ($validated['service_area_type'] === Place::SERVICE_AREA_RADIUS) {
                $validated['area_geojson'] = null;
            }
            if ($validated['service_area_type'] === Place::SERVICE_AREA_POLYGON) {
                $validated['radius_meters'] = null;
            }
        }

        $place->update($validated);

        if ($request->hasFile('logo')) {
            if ($place->logo_path) {
                Storage::disk('public')->delete($place->logo_path);
            }
            $place->logo_path = $request->file('logo')->store('places/'.$place->id, 'public');
            $place->save();
        } elseif ($request->boolean('remove_logo')) {
            if ($place->logo_path) {
                Storage::disk('public')->delete($place->logo_path);
            }
            $place->logo_path = null;
            $place->save();
        }

        $place->refresh();
        $uid = (int) $request->user()->id;
        $place->load([
            'offers',
            'administrators' => fn ($q) => $q->where('users.id', $uid),
        ]);

        return response()->json([
            'place' => new PlaceResource($place),
        ]);
    }

    public function destroy(Request $request, Place $place): JsonResponse
    {
        $this->authorize('delete', $place);

        if ($place->logo_path) {
            Storage::disk('public')->delete($place->logo_path);
        }

        $place->delete();

        return response()->json(['ok' => true]);
    }
}
