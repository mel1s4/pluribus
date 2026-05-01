<?php

namespace App\Http\Middleware;

use App\Support\ActiveCommunityContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveCommunity
{
    public function __construct(
        private readonly ActiveCommunityContext $context,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->context->resolveSlug($request);
        $request->attributes->set('active_community_slug', $slug);
        $request->attributes->set('active_community', $this->context->resolveCommunity($request));

        return $next($request);
    }
}
