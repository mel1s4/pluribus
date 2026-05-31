<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityPublicLegalDocumentsController extends Controller
{
    /**
     * Public read of community-authored legal markdown (for join flows and public pages).
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'community' => [
                'name' => $community->name,
                'slug' => $community->slug,
                'terms_markdown' => $community->terms_markdown,
                'privacy_policy_markdown' => $community->privacy_policy_markdown,
            ],
        ]);
    }
}
