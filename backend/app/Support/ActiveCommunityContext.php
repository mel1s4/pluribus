<?php

namespace App\Support;

use App\Models\Community;
use Illuminate\Http\Request;

class ActiveCommunityContext
{
    public function resolveSlug(Request $request): ?string
    {
        $routeSlug = $request->route('communitySlug');
        if (is_string($routeSlug) && $routeSlug !== '') {
            return $routeSlug;
        }

        $headerSlug = $request->header('X-Community-Slug');
        if (is_string($headerSlug) && $headerSlug !== '') {
            return $headerSlug;
        }

        return null;
    }

    public function resolveCommunity(Request $request): ?Community
    {
        $slug = $this->resolveSlug($request);
        if ($slug === null) {
            return null;
        }

        return Community::query()->where('slug', $slug)->first();
    }
}
