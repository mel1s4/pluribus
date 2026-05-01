<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entries per sealed block
    |--------------------------------------------------------------------------
    |
    | When a block accumulates this many entries it is sealed (Merkle root,
    | commitment, Ed25519 signature). Default 1 signs every movement immediately.
    |
    */
    'entries_per_block' => (int) env('WALLET_LEDGER_ENTRIES_PER_BLOCK', 1),

    /*
    |--------------------------------------------------------------------------
    | Operator Ed25519 secret key (base64, 64 bytes libsodium secret key)
    |--------------------------------------------------------------------------
    */
    'secret_key' => env('WALLET_LEDGER_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Optional public key override (base64, 32 bytes)
    |--------------------------------------------------------------------------
    |
    | If empty, derived from the secret key at runtime.
    |
    */
    'public_key' => env('WALLET_LEDGER_PUBLIC_KEY'),

];
