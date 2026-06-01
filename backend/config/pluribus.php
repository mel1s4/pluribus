<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform SPA hosts
    |--------------------------------------------------------------------------
    |
    | HTTP Host values that serve the main Pluribus application (not a single
    | community custom domain). Requests from these hosts do not resolve an
    | active community from the Host header.
    |
    */

    'platform_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('PLURIBUS_PLATFORM_HOSTS', 'localhost,127.0.0.1,pluribus.vzs.mx,www.pluribus.vzs.mx,chante.vzs.mx,www.chante.vzs.mx'))
    ))),

];
