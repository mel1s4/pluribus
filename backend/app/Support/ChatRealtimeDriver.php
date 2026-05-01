<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;
use Throwable;

final class ChatRealtimeDriver
{
    /**
     * @return 'redis'|'database'
     */
    public static function resolve(): string
    {
        $driver = (string) config('chat.realtime_driver', 'auto');

        if ($driver === 'redis') {
            return 'redis';
        }

        if ($driver === 'database') {
            return 'database';
        }

        try {
            Redis::connection()->ping();

            return 'redis';
        } catch (Throwable) {
            return 'database';
        }
    }
}
