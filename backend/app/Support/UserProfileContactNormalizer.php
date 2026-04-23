<?php

namespace App\Support;

class UserProfileContactNormalizer
{
    public const MAX_STRING_LIST_ITEMS = 20;

    public const MAX_EXTERNAL_LINKS = 20;

    /**
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }
            $s = trim((string) $item);
            if ($s === '') {
                continue;
            }
            $out[] = $s;
            if (count($out) >= self::MAX_STRING_LIST_ITEMS) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    public static function externalLinks(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = isset($row['title']) && is_string($row['title']) ? trim($row['title']) : '';
            $url = isset($row['url']) && is_string($row['url']) ? trim($row['url']) : '';
            if ($title === '' && $url === '') {
                continue;
            }
            $out[] = ['title' => $title, 'url' => $url];
            if (count($out) >= self::MAX_EXTERNAL_LINKS) {
                break;
            }
        }

        return $out;
    }
}
