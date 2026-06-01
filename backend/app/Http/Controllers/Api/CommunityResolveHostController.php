<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Support\ActiveCommunityContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityResolveHostController extends Controller
{
    public function __construct(
        private readonly ActiveCommunityContext $context,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        if ($this->context->isPlatformRequest($request)) {
            return response()->json([
                'mode' => 'platform',
                'community' => null,
            ]);
        }

        $community = $this->context->resolveCommunityFromHost($request);
        if ($community === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $payload = (new CommunityResource($community))->toArray($request);

        return response()->json([
            'mode' => 'community',
            'community' => [
                'id' => $payload['id'],
                'name' => $payload['name'],
                'slug' => $payload['slug'],
                'logo_url' => $payload['logo_url'],
                'default_language' => $payload['default_language'],
            ],
        ]);
    }
}
