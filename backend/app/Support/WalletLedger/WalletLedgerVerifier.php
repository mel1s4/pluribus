<?php

namespace App\Support\WalletLedger;

use App\Models\WalletLedgerBlock;
use App\Models\WalletLedgerEntry;

/**
 * Offline-style verification helpers for auditors and tests.
 */
final class WalletLedgerVerifier
{
    /**
     * Verify a sealed block's Merkle root, commitment, and detached signature.
     */
    public static function verifySealedBlock(WalletLedgerBlock $block, string $publicKeyBase64): bool
    {
        if ($block->sealed_at === null || $block->block_commitment === null || $block->operator_signature === null || $block->merkle_root === null) {
            return false;
        }

        $entries = WalletLedgerEntry::query()
            ->where('wallet_ledger_block_id', $block->id)
            ->orderBy('position_in_block')
            ->get();

        if ($entries->isEmpty()) {
            return false;
        }

        foreach ($entries as $e) {
            if (strlen((string) $e->leaf_hash) !== 64 || ! ctype_xdigit((string) $e->leaf_hash)) {
                return false;
            }
            if (CanonicalLedgerEntry::leafHashHex($e) !== $e->leaf_hash) {
                return false;
            }
        }

        $merkle = MerkleTree::rootHex($entries->map(fn (WalletLedgerEntry $e) => (string) $e->leaf_hash)->all());
        if ($merkle !== $block->merkle_root) {
            return false;
        }

        $commitment = BlockHasher::commitmentHex(
            (int) $block->community_id,
            (int) $block->height,
            $block->prev_commitment,
            $merkle,
            (int) $block->first_entry_id,
            (int) $block->last_entry_id,
        );

        if ($commitment !== $block->block_commitment) {
            return false;
        }

        $pub = base64_decode($publicKeyBase64, true);
        if ($pub === false || strlen($pub) !== \SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        $sig = base64_decode((string) $block->operator_signature, true);
        if ($sig === false || strlen($sig) !== \SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return LedgerSigner::verifyDetached($commitment, $sig, $pub);
    }

    /**
     * Verify prev_commitment chain for sealed blocks in height order.
     *
     * @return list<string> Empty if OK, otherwise human-readable error strings
     */
    public static function verifyChainLinks(int $communityId): array
    {
        $errors = [];
        $blocks = WalletLedgerBlock::query()
            ->where('community_id', $communityId)
            ->orderBy('height')
            ->get();

        $prevExpected = Genesis::PREV_COMMITMENT_HEX;
        foreach ($blocks as $block) {
            if ($block->prev_commitment !== $prevExpected) {
                $errors[] = 'prev_commitment mismatch at height '.$block->height;
            }
            if ($block->sealed_at !== null && $block->block_commitment !== null) {
                $prevExpected = $block->block_commitment;
            }
        }

        return $errors;
    }
}
