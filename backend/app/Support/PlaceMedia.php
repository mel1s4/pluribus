<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PlaceMedia
{
    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
