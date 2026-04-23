<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceRequirementResponseRequest;
use App\Http\Resources\PlaceRequirementResponseResource;
use App\Models\Place;
use App\Models\PlaceRequirement;
use App\Models\PlaceRequirementResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PlaceRequirementResponseController extends Controller
{
    public function store(StorePlaceRequirementResponseRequest $request, Place $place, PlaceRequirement $requirement): JsonResponse
    {
        $this->authorize('view', $place);
        if ((int) $requirement->place_id !== (int) $place->id) {
            abort(404);
        }

        $validated = $request->validated();
        $dir = 'places/'.$place->id.'/requirements/'.$requirement->id.'/responses';

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

        $row = $requirement->responses()->create([
            'user_id' => (int) $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'photo_path' => $photoPath,
            'gallery_paths' => $galleryPaths === [] ? null : $galleryPaths,
            'tags' => $tags === [] ? null : $tags,
            'visibility' => $validated['visibility'],
        ]);

        $row->load('user');

        return response()->json([
            'response' => new PlaceRequirementResponseResource($row),
        ], 201);
    }

    public function destroy(Request $request, Place $place, PlaceRequirement $requirement, PlaceRequirementResponse $response): JsonResponse
    {
        $this->authorize('view', $place);
        if ((int) $requirement->place_id !== (int) $place->id) {
            abort(404);
        }
        if ((int) $response->place_requirement_id !== (int) $requirement->id) {
            abort(404);
        }

        $uid = (int) $request->user()->id;
        if ((int) $response->user_id !== $uid && ! Gate::forUser($request->user())->allows('update', $place)) {
            abort(403);
        }

        if ($response->photo_path) {
            Storage::disk('public')->delete($response->photo_path);
        }
        foreach ($response->gallery_paths ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }
        $response->delete();

        return response()->json(['ok' => true]);
    }
}
