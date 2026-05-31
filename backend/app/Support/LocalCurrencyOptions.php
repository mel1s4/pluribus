<?php

namespace App\Support;

final class LocalCurrencyOptions
{
    public const MXN = 'MXN';

    public const USD = 'USD';

    public const EUR = 'EUR';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return [
            self::MXN,
            self::USD,
            self::EUR,
        ];
    }
}
