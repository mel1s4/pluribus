<?php

namespace App\Support;

final class LocaleOptions
{
    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return ['en', 'es'];
    }

    public static function default(): string
    {
        return 'en';
    }
}
