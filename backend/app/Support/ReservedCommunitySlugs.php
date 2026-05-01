<?php

namespace App\Support;

final class ReservedCommunitySlugs
{
    /**
     * Slugs reserved for app routes (URLs, legacy redirects).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'settings',
            'leadership',
            'new',
            'dashboard',
            'wallet',
            'communities',
            'community',
            'community-settings',
            'my-communities',
            'login',
            'join',
            'place',
            'places',
            'api',
            'sanctum',
        ];
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::all(), true);
    }
}
