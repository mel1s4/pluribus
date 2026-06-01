<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Community;
use App\Support\CommunityHost;
use Illuminate\Http\Request;

trait ResolvesRequestCommunity
{
    protected function activeCommunity(Request $request): ?Community
    {
        $active = $request->attributes->get('active_community');

        return $active instanceof Community ? $active : null;
    }

    protected function resolveCommunityForWrite(Request $request): Community
    {
        $active = $this->activeCommunity($request);
        if ($active instanceof Community) {
            return $active;
        }

        $host = CommunityHost::normalize($request->getHost());
        if ($host !== null && ! CommunityHost::isPlatformHost($host)) {
            abort(403, 'Unknown community host.');
        }

        return Community::current();
    }

    protected function resolveCommunityIdForWrite(Request $request): int
    {
        return (int) $this->resolveCommunityForWrite($request)->id;
    }
}
