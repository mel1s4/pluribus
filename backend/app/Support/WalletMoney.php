<?php

namespace App\Support;

final class WalletMoney
{
    private const SCALE = 2;

    public static function normalize(string|float|int $amount): string
    {
        return number_format((float) $amount, self::SCALE, '.', '');
    }

    /**
     * Fixed-scale (2 dp) money as integer cents. Avoids ext-bcmath; callers pass amounts
     * compatible with {@see normalize} (e.g. DB balances, normalized inputs).
     */
    private static function toCents(string $amount): int
    {
        $n = self::normalize($amount);
        if (preg_match('/^(-?)(\d+)\.(\d{2})$/', $n, $m) !== 1) {
            throw new \InvalidArgumentException('Invalid amount: '.$n);
        }
        $sign = $m[1] === '-' ? -1 : 1;

        return $sign * (((int) $m[2]) * 100 + (int) $m[3]);
    }

    private static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $abs = $negative ? -$cents : $cents;
        $whole = intdiv($abs, 100);
        $frac = $abs % 100;

        return ($negative ? '-' : '').$whole.'.'.str_pad((string) $frac, 2, '0', STR_PAD_LEFT);
    }

    public static function add(string $a, string $b): string
    {
        return self::fromCents(self::toCents($a) + self::toCents($b));
    }

    public static function sub(string $a, string $b): string
    {
        return self::fromCents(self::toCents($a) - self::toCents($b));
    }

    public static function compare(string $a, string $b): int
    {
        return self::toCents($a) <=> self::toCents($b);
    }

    public static function isPositive(string $amount): bool
    {
        return self::compare($amount, '0.00') > 0;
    }
}
