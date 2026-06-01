<?php

namespace App\Support;

use Illuminate\Http\Request;

class CommunityHost
{
    /**
     * Hostname of the community SPA the user is browsing (not the API host).
     */
    public static function spaHostFromRequest(Request $request): ?string
    {
        $header = $request->header('X-Community-Host');
        if (is_string($header) && trim($header) !== '') {
            return self::normalize($header);
        }

        $query = $request->query('host');
        if (is_string($query) && trim($query) !== '') {
            return self::normalize($query);
        }

        return self::normalize($request->getHost());
    }

    public static function normalize(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host !== '' ? $host : null;
    }

    public static function isPlatformHost(?string $host): bool
    {
        $normalized = self::normalize($host);
        if ($normalized === null) {
            return true;
        }

        return in_array($normalized, config('pluribus.platform_hosts', []), true);
    }
}
