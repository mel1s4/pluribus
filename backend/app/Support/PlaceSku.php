<?php

namespace App\Support;

use Illuminate\Support\Str;

class PlaceSku
{
    public static function normalize(?string $value): string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return '';
        }
        $slug = Str::lower(Str::slug($trimmed, '-'));

        return Str::limit($slug, 64, '');
    }

    public static function generate(?string $source): string
    {
        $normalized = self::normalize($source);

        return $normalized !== '' ? $normalized : Str::lower(Str::random(12));
    }
}
