<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceAdministratorRequest;
use App\Http\Requests\UpdatePlaceAdministratorRequest;
use App\Http\Resources\PlaceAdministratorResource;
use App\Models\Place;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlaceAdministratorController extends Controller
{
    public function index(Request $request, Place $place): AnonymousResourceCollection
    {
        $this->authorize('manageAdmins', $place);

        $admins = $place->administrators()
            ->orderBy('name')
            ->get();

        return PlaceAdministratorResource::collection($admins);
    }

    public function store(StorePlaceAdministratorRequest $request, Place $place): JsonResponse
    {
        $this->authorize('manageAdmins', $place);

        $userId = (int) $request->validated('user_id');
        if ($userId === (int) $place->user_id) {
            return response()->json([
                'message' => 'The place owner already has access and cannot be added as an administrator.',
            ], 422);
        }

        if ($place->administrators()->where('users.id', $userId)->exists()) {
            return response()->json([
                'message' => 'This user is already an administrator for this place.',
            ], 422);
        }

        $place->administrators()->attach($userId, [
            'role' => $request->validated('role'),
        ]);

        $user = $place->administrators()->where('users.id', $userId)->first();
        assert($user instanceof User);

        return response()->json([
            'administrator' => new PlaceAdministratorResource($user),
        ], 201);
    }

    public function update(UpdatePlaceAdministratorRequest $request, Place $place, User $user): JsonResponse
    {
        $this->authorize('manageAdmins', $place);

        if (! $place->administrators()->where('users.id', $user->id)->exists()) {
            abort(404);
        }

        if ((int) $user->id === (int) $place->user_id) {
            return response()->json([
                'message' => 'The place owner is not stored as an administrator row.',
            ], 422);
        }

        $place->administrators()->updateExistingPivot($user->id, [
            'role' => $request->validated('role'),
        ]);

        $fresh = $place->administrators()->where('users.id', $user->id)->first();
        assert($fresh instanceof User);

        return response()->json([
            'administrator' => new PlaceAdministratorResource($fresh),
        ]);
    }

    public function destroy(Request $request, Place $place, User $user): JsonResponse
    {
        $this->authorize('manageAdmins', $place);

        if (! $place->administrators()->where('users.id', $user->id)->exists()) {
            abort(404);
        }

        if ((int) $user->id === (int) $place->user_id) {
            return response()->json([
                'message' => 'The place owner cannot be removed.',
            ], 422);
        }

        $place->administrators()->detach($user->id);

        return response()->json(['ok' => true]);
    }
}
