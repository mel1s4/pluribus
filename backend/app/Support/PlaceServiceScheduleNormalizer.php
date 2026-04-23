<?php

namespace App\Support;

class PlaceServiceScheduleNormalizer
{
    /** @var list<string> */
    public const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const MAX_SLOTS_PER_DAY = 12;

    /**
     * @return array<string, list<array{open: string, close: string}>>
     */
    public static function normalize(mixed $input): array
    {
        $out = [];
        foreach (self::DAY_KEYS as $day) {
            $out[$day] = [];
        }
        if (! is_array($input)) {
            return $out;
        }
        foreach (self::DAY_KEYS as $day) {
            if (! isset($input[$day]) || ! is_array($input[$day])) {
                continue;
            }
            $slots = [];
            foreach ($input[$day] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                $open = isset($slot['open']) && is_string($slot['open']) ? trim($slot['open']) : '';
                $close = isset($slot['close']) && is_string($slot['close']) ? trim($slot['close']) : '';
                if ($open === '' || $close === '') {
                    continue;
                }
                $slots[] = ['open' => $open, 'close' => $close];
                if (count($slots) >= self::MAX_SLOTS_PER_DAY) {
                    break;
                }
            }
            $out[$day] = $slots;
        }

        return $out;
    }
}
