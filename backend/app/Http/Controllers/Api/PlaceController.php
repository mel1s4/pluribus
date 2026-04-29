<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use App\Models\User;
use App\Support\PlaceServiceScheduleNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PlaceController extends Controller
{
    public function mapIndex(Request $request): AnonymousResourceCollection
    {
        $places = Place::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get();

        return PlaceResource::collection($places);
    }

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

        $schedule = array_key_exists('service_schedule', $validated)
            ? PlaceServiceScheduleNormalizer::normalize($validated['service_schedule'])
            : PlaceServiceScheduleNormalizer::normalize([]);

        $place = Place::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'is_public' => (bool) ($validated['is_public'] ?? false),
            'description' => $validated['description'] ?? null,
            'tags' => $tags === [] ? null : $tags,
            'latitude' => null,
            'longitude' => null,
            'location_type' => Place::LOCATION_NONE,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'service_schedule' => $this->scheduleOrNull($schedule),
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
        $canManage = $request->user()->can('update', $place);
        $place->load([
            'offers' => function ($q) use ($uid, $canManage): void {
                $q->with('audiences:id')->orderBy('id');
                if (! $canManage) {
                    $q->visibleToUser($uid);
                }
            },
            'requirements' => function ($q) use ($uid, $canManage): void {
                $q->with(['responses.user', 'exampleOffer.place', 'audiences:id'])->orderBy('id');
                if (! $canManage) {
                    $q->visibleToUser($uid);
                }
            },
            'administrators' => fn ($q) => $q->where('users.id', $uid),
        ]);
        foreach ($place->requirements as $req) {
            $req->setRelation('place', $place);
        }

        return response()->json([
            'place' => new PlaceResource($place),
        ]);
    }

    /**
     * Storefront / unauthenticated-friendly place detail (same JSON shape as {@see show} when allowed).
     */
    public function showPublic(Request $request, Place $place): JsonResponse
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $place), 403);

        $user = $request->user();
        $uid = $user instanceof User ? (int) $user->id : 0;
        $canManage = $user instanceof User && $user->can('update', $place);

        $relations = [
            'offers' => function ($q) use ($uid, $canManage): void {
                $q->with('audiences:id')->orderBy('id');
                if (! $canManage) {
                    $q->visibleToUser($uid);
                }
            },
            'requirements' => function ($q) use ($uid, $canManage): void {
                $q->with(['responses.user', 'exampleOffer.place', 'audiences:id'])->orderBy('id');
                if (! $canManage) {
                    $q->visibleToUser($uid);
                }
            },
        ];
        if ($user instanceof User) {
            $relations['administrators'] = fn ($q) => $q->where('users.id', $uid);
        }

        $place->load($relations);
        foreach ($place->requirements as $req) {
            $req->setRelation('place', $place);
        }

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

        $effectiveSat = $validated['service_area_type'] ?? $place->service_area_type;
        if ($effectiveSat === Place::SERVICE_AREA_RADIUS || $effectiveSat === Place::SERVICE_AREA_POLYGON) {
            $validated['location_type'] = Place::LOCATION_POINT;
        }

        if (array_key_exists('service_schedule', $validated)) {
            $validated['service_schedule'] = $this->scheduleOrNull($validated['service_schedule']);
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
            'offers' => fn ($q) => $q->with('audiences:id')->orderBy('id'),
            'requirements' => fn ($q) => $q->with(['responses.user', 'exampleOffer.place', 'audiences:id'])->orderBy('id'),
            'administrators' => fn ($q) => $q->where('users.id', $uid),
        ]);
        foreach ($place->requirements as $req) {
            $req->setRelation('place', $place);
        }

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

    /**
     * @param  array<string, list<array{open: string, close: string}>>  $normalized
     */
    private function scheduleOrNull(array $normalized): ?array
    {
        foreach ($normalized as $slots) {
            if ($slots !== []) {
                return $normalized;
            }
        }

        return null;
    }
}
