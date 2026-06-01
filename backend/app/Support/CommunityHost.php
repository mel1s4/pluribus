<?php

namespace App\Support;

class CommunityHost
{
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
