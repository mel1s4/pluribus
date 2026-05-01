<?php

namespace App\Support\WalletLedger;

/**
 * 32-byte zero commitment as 64 lowercase hex chars (prev for first block).
 */
final class Genesis
{
    public const PREV_COMMITMENT_HEX = '0000000000000000000000000000000000000000000000000000000000000000';
}
