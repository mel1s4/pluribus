<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat SSE realtime delivery
    |--------------------------------------------------------------------------
    |
    | Controls GET /api/chats/stream and how MessageSent reaches connected clients.
    |
    | - redis: Redis PUBLISH/SUBSCRIBE (needs a working Redis server).
    | - database: Poll the database for new chat_messages rows (no Redis).
    | - auto: Try Redis; if unavailable, use database.
    |
    */

    'realtime_driver' => env('CHAT_REALTIME_DRIVER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Database driver: poll interval (seconds)
    |--------------------------------------------------------------------------
    |
    | Used when realtime_driver is database or auto falls back to database.
    | Lower is snappier but more DB load.
    |
    */

    'database_poll_seconds' => (int) env('CHAT_DATABASE_POLL_SECONDS', 2),

];
