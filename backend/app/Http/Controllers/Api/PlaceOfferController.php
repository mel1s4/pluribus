<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceOfferRequest;
use App\Http\Requests\UpdatePlaceOfferRequest;
use App\Http\Resources\PlaceOfferResource;
use App\Models\Place;
use App\Models\PlaceOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class PlaceOfferController extends Controller
{
    public function index(Request $request, Place $place): AnonymousResourceCollection
    {
        $this->authorize('view', $place);

        $offers = $place->offers()->orderBy('id')->get();

        return PlaceOfferResource::collection($offers);
    }

    public function store(StorePlaceOfferRequest $request, Place $place): JsonResponse
    {
        $this->authorize('update', $place);

        $validated = $request->validated();
        $dir = 'places/'.$place->id.'/offers';

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

        $offer = $place->offers()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'photo_path' => $photoPath,
            'gallery_paths' => $galleryPaths === [] ? null : $galleryPaths,
            'tags' => $tags === [] ? null : $tags,
        ]);

        return response()->json([
            'offer' => new PlaceOfferResource($offer),
        ], 201);
    }

    public function update(UpdatePlaceOfferRequest $request, Place $place, PlaceOffer $offer): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $offer->place_id !== (int) $place->id) {
            abort(404);
        }

        $validated = $request->validated();
        $dir = 'places/'.$place->id.'/offers';

        if ($request->hasFile('photo')) {
            if ($offer->photo_path) {
                Storage::disk('public')->delete($offer->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store($dir, 'public');
        }

        $galleryPaths = $offer->gallery_paths ?? [];
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
            $offer->tags = $tags === [] ? null : $tags;
        }

        $offer->fill([
            'title' => $validated['title'] ?? $offer->title,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $offer->description,
            'price' => $validated['price'] ?? $offer->price,
        ]);
        if (array_key_exists('photo_path', $validated)) {
            $offer->photo_path = $validated['photo_path'];
        }
        $offer->gallery_paths = $galleryPaths === [] ? null : $galleryPaths;
        $offer->save();

        return response()->json([
            'offer' => new PlaceOfferResource($offer->fresh()),
        ]);
    }

    public function destroy(Request $request, Place $place, PlaceOffer $offer): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $offer->place_id !== (int) $place->id) {
            abort(404);
        }

        if ($offer->photo_path) {
            Storage::disk('public')->delete($offer->photo_path);
        }
        foreach ($offer->gallery_paths ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }
        $offer->delete();

        return response()->json(['ok' => true]);
    }
}
