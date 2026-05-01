<?php

namespace App\Support;

final class WalletMoney
{
    private const SCALE = 2;

    public static function normalize(string|float|int $amount): string
    {
        return number_format((float) $amount, self::SCALE, '.', '');
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isPositive(string $amount): bool
    {
        return self::compare($amount, '0.00') > 0;
    }
}
