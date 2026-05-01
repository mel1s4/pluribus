<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum personification wall-clock duration (seconds)
    |--------------------------------------------------------------------------
    */
    'max_seconds' => (int) env('PERSONIFICATION_MAX_SECONDS', 4 * 3600),

    /*
    |--------------------------------------------------------------------------
    | Idle timeout: no requests refresh for this long ends personification
    |--------------------------------------------------------------------------
    */
    'idle_seconds' => (int) env('PERSONIFICATION_IDLE_SECONDS', 30 * 60),

    /*
    |--------------------------------------------------------------------------
    | Session touch interval (seconds)
    |--------------------------------------------------------------------------
    | Throttles personification session writes under DB session driver load.
    */
    'touch_interval_seconds' => (int) env('PERSONIFICATION_TOUCH_INTERVAL_SECONDS', 30),

];
