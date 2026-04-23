<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberProfileResource;
use App\Http\Resources\PlaceDirectoryResource;
use App\Models\Place;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberProfileController extends Controller
{
    public function show(Request $request, User $user): JsonResponse
    {
        $places = Place::query()
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->orWhereHas('administrators', fn ($q2) => $q2->where('users.id', $user->id));
            })
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'member' => (new MemberProfileResource($user))->toArray($request),
            'places' => $places->map(
                static fn (Place $place): array => (new PlaceDirectoryResource($place))->toArray($request)
            )->values()->all(),
        ]);
    }
}
