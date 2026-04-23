<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceRequirementRequest;
use App\Http\Requests\UpdatePlaceRequirementRequest;
use App\Http\Resources\PlaceRequirementResource;
use App\Models\Place;
use App\Models\PlaceRequirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class PlaceRequirementController extends Controller
{
    public function index(Request $request, Place $place): AnonymousResourceCollection
    {
        $this->authorize('view', $place);
        $uid = (int) $request->user()->id;
        $canManage = $request->user()->can('update', $place);

        $rowsQuery = $place->requirements()
            ->with([
                'responses.user',
                'exampleOffer.place',
                'audiences:id',
            ])
            ->orderBy('id');
        if (! $canManage) {
            $rowsQuery->visibleToUser($uid);
        }
        $rows = $rowsQuery->get();

        foreach ($rows as $row) {
            $row->setRelation('place', $place);
        }

        return PlaceRequirementResource::collection($rows);
    }

    public function store(StorePlaceRequirementRequest $request, Place $place): JsonResponse
    {
        $this->authorize('update', $place);

        $validated = $request->validated();
        $dir = 'places/'.$place->id.'/requirements';

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store($dir, 'public');
        }

        $galleryPaths = [];
        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery', []) as $file) {
                if ($file !== null && $file->isValid()) {
                    $galleryPaths[] = $file->store($dir.'/gallery', 'public');
                }
            }
        }

        $tags = $validated['tags'] ?? [];
        $tags = is_array($tags) ? array_values(array_filter(array_map('strval', $tags), fn (string $t) => $t !== '')) : [];

        $mode = $validated['recurrence_mode'];
        $weekdays = null;
        if ($mode === PlaceRequirement::RECURRENCE_WEEKLY) {
            $wd = $validated['recurrence_weekdays'] ?? [];
            $wd = is_array($wd) ? array_values(array_unique(array_map('strval', $wd))) : [];
            $weekdays = $wd === [] ? null : $wd;
        }

        $row = $place->requirements()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'],
            'recurrence_mode' => $mode,
            'recurrence_weekdays' => $weekdays,
            'photo_path' => $photoPath,
            'gallery_paths' => $galleryPaths === [] ? null : $galleryPaths,
            'tags' => $tags === [] ? null : $tags,
            'example_place_offer_id' => $validated['example_place_offer_id'] ?? null,
            'visibility_scope' => $validated['visibility_scope'] ?? PlaceRequirement::VISIBILITY_SCOPE_PUBLIC,
        ]);
        $row->audiences()->sync(
            ($validated['visibility_scope'] ?? PlaceRequirement::VISIBILITY_SCOPE_PUBLIC) === PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE
                ? ($validated['audience_ids'] ?? [])
                : []
        );

        $row->load(['responses.user', 'exampleOffer.place', 'audiences:id']);
        $row->setRelation('place', $place);

        return response()->json([
            'requirement' => new PlaceRequirementResource($row),
        ], 201);
    }

    public function update(UpdatePlaceRequirementRequest $request, Place $place, PlaceRequirement $requirement): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $requirement->place_id !== (int) $place->id) {
            abort(404);
        }

        $validated = $request->validated();
        $dir = 'places/'.$place->id.'/requirements';

        if ($request->hasFile('photo')) {
            if ($requirement->photo_path) {
                Storage::disk('public')->delete($requirement->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store($dir, 'public');
        }

        $galleryPaths = $requirement->gallery_paths ?? [];
        if ($request->has('remove_gallery_indices')) {
            $indices = array_unique(array_map('intval', $request->input('remove_gallery_indices', [])));
            rsort($indices);
            foreach ($indices as $i) {
                if (isset($galleryPaths[$i])) {
                    Storage::disk('public')->delete($galleryPaths[$i]);
                    unset($galleryPaths[$i]);
                }
            }
            $galleryPaths = array_values($galleryPaths);
        }

        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery', []) as $file) {
                if ($file !== null && $file->isValid()) {
                    $galleryPaths[] = $file->store($dir.'/gallery', 'public');
                }
            }
        }

        if (array_key_exists('tags', $validated)) {
            $tags = $validated['tags'] ?? [];
            $tags = is_array($tags) ? array_values(array_filter(array_map('strval', $tags), fn (string $t) => $t !== '')) : [];
            $requirement->tags = $tags === [] ? null : $tags;
        }

        $mode = $validated['recurrence_mode'] ?? $requirement->recurrence_mode;
        $weekdays = $requirement->recurrence_weekdays;
        if (array_key_exists('recurrence_mode', $validated) || array_key_exists('recurrence_weekdays', $validated)) {
            if ($mode === PlaceRequirement::RECURRENCE_WEEKLY) {
                $wd = $validated['recurrence_weekdays'] ?? $requirement->recurrence_weekdays ?? [];
                $wd = is_array($wd) ? array_values(array_unique(array_map('strval', $wd))) : [];
                $weekdays = $wd === [] ? null : $wd;
            } else {
                $weekdays = null;
            }
        }

        $requirement->fill([
            'title' => $validated['title'] ?? $requirement->title,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $requirement->description,
            'quantity' => $validated['quantity'] ?? $requirement->quantity,
            'unit' => $validated['unit'] ?? $requirement->unit,
            'recurrence_mode' => $mode,
            'recurrence_weekdays' => $weekdays,
            'visibility_scope' => $validated['visibility_scope'] ?? $requirement->visibility_scope,
        ]);
        if (array_key_exists('photo_path', $validated)) {
            $requirement->photo_path = $validated['photo_path'];
        }
        if (array_key_exists('example_place_offer_id', $validated)) {
            $requirement->example_place_offer_id = $validated['example_place_offer_id'];
        }
        $requirement->gallery_paths = $galleryPaths === [] ? null : $galleryPaths;
        $requirement->save();
        $scope = $validated['visibility_scope'] ?? $requirement->visibility_scope;
        if ($scope === PlaceRequirement::VISIBILITY_SCOPE_AUDIENCE) {
            $requirement->audiences()->sync($validated['audience_ids'] ?? $requirement->audiences()->pluck('place_audiences.id')->all());
        } elseif (array_key_exists('visibility_scope', $validated)) {
            $requirement->audiences()->sync([]);
        }

        $fresh = $requirement->fresh(['responses.user', 'exampleOffer.place', 'audiences:id']);
        $fresh->setRelation('place', $place);

        return response()->json([
            'requirement' => new PlaceRequirementResource($fresh),
        ]);
    }

    public function destroy(Request $request, Place $place, PlaceRequirement $requirement): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $requirement->place_id !== (int) $place->id) {
            abort(404);
        }

        if ($requirement->photo_path) {
            Storage::disk('public')->delete($requirement->photo_path);
        }
        foreach ($requirement->gallery_paths ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }
        $requirement->delete();

        return response()->json(['ok' => true]);
    }
}
