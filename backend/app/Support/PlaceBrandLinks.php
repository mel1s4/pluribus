<?php

namespace App\Support;

final class PlaceBrandLinks
{
    public const MAX_LINKS = 20;

    /** @var list<string> */
    public const ICON_KEYS = [
        'website',
        'instagram',
        'facebook',
        'tiktok',
        'x',
        'youtube',
        'linkedin',
        'whatsapp',
        'telegram',
    ];

    /**
     * @param  mixed  $raw
     * @return list<array{title: string, url: string, icon: string}>
     */
    public static function normalize($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $links = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $title = trim((string) ($entry['title'] ?? ''));
            $url = trim((string) ($entry['url'] ?? ''));
            $icon = trim((string) ($entry['icon'] ?? ''));
            if ($title === '' || $url === '' || ! in_array($icon, self::ICON_KEYS, true)) {
                continue;
            }

            $links[] = [
                'title' => $title,
                'url' => $url,
                'icon' => $icon,
            ];

            if (count($links) >= self::MAX_LINKS) {
                break;
            }
        }

        return $links;
    }
}
