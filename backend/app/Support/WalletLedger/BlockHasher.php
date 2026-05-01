<?php

namespace App\Support\WalletLedger;

/**
 * 32-byte block commitment as 64-char hex SHA-256 over a canonical UTF-8 string.
 */
final class BlockHasher
{
    public static function commitmentHex(
        int $communityId,
        int $height,
        string $prevCommitmentHex,
        string $merkleRootHex,
        int $firstEntryId,
        int $lastEntryId,
    ): string {
        $payload = implode('|', [
            'wallet_ledger_block_v1',
            (string) $communityId,
            (string) $height,
            $prevCommitmentHex,
            $merkleRootHex,
            (string) $firstEntryId,
            (string) $lastEntryId,
        ]);

        return hash('sha256', $payload, false);
    }
}
