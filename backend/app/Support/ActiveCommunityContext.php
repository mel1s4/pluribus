<?php

namespace App\Support;

use App\Models\Community;
use App\Models\CommunityDomain;
use Illuminate\Http\Request;

class ActiveCommunityContext
{
    public function resolveSlug(Request $request): ?string
    {
        $routeSlug = $request->route('communitySlug');
        if (is_string($routeSlug) && $routeSlug !== '') {
            return $routeSlug;
        }

        $slugParam = $request->route('slug');
        if (is_string($slugParam) && $slugParam !== '') {
            return $slugParam;
        }

        $headerSlug = $request->header('X-Community-Slug');
        if (is_string($headerSlug) && $headerSlug !== '') {
            return trim($headerSlug);
        }

        $community = $this->resolveCommunityFromHost($request);
        if ($community !== null) {
            return (string) $community->slug;
        }

        return null;
    }

    public function resolveCommunity(Request $request): ?Community
    {
        $slug = $request->route('communitySlug');
        if (! is_string($slug) || $slug === '') {
            $slug = $request->route('slug');
        }
        if (is_string($slug) && $slug !== '') {
            $fromRoute = Community::query()->where('slug', $slug)->first();
            if ($fromRoute !== null) {
                return $fromRoute;
            }
        }

        $headerSlug = $request->header('X-Community-Slug');
        if (is_string($headerSlug) && trim($headerSlug) !== '') {
            $fromHeader = Community::query()->where('slug', trim($headerSlug))->first();
            if ($fromHeader !== null) {
                return $fromHeader;
            }
        }

        return $this->resolveCommunityFromHost($request);
    }

    public function resolveCommunityFromHost(Request $request): ?Community
    {
        $host = CommunityHost::normalize($request->getHost());
        if ($host === null || CommunityHost::isPlatformHost($host)) {
            return null;
        }

        $domain = CommunityDomain::query()->where('host', $host)->first();
        if ($domain === null) {
            return null;
        }

        return Community::query()->find($domain->community_id);
    }

    public function isPlatformRequest(Request $request): bool
    {
        return CommunityHost::isPlatformHost($request->getHost());
    }
}
