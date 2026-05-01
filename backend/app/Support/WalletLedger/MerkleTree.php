<?php

namespace App\Support\WalletLedger;

/**
 * Binary SHA-256 Merkle tree over leaf digests (32-byte binary).
 * Odd level: duplicate last node (Bitcoin-style).
 */
final class MerkleTree
{
    /**
     * @param  list<string>  $leafHashesHex  Lowercase 64-char hex SHA-256 leaves in block order
     */
    public static function rootHex(array $leafHashesHex): string
    {
        if ($leafHashesHex === []) {
            return hash('sha256', 'wallet_ledger_merkle_empty_v1', false);
        }

        $level = [];
        foreach ($leafHashesHex as $hex) {
            $level[] = hex2bin($hex);
        }

        while (count($level) > 1) {
            $next = [];
            $count = count($level);
            for ($i = 0; $i < $count; $i += 2) {
                $left = $level[$i];
                $right = ($i + 1 < $count) ? $level[$i + 1] : $left;
                $next[] = hash('sha256', $left.$right, true);
            }
            $level = $next;
        }

        return bin2hex($level[0]);
    }
}
