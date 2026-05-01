<?php

namespace App\Support\WalletLedger;

use App\Models\WalletLedgerEntry;

/**
 * JSON shape for audit ledger rows (shared by WalletController and community export).
 */
final class LedgerAuditRowFormatter
{
    /**
     * @return array<string, mixed>
     */
    public static function format(WalletLedgerEntry $tx): array
    {
        $block = $tx->ledgerBlock;
        if ($block === null) {
            throw new \InvalidArgumentException('WalletLedgerEntry must load ledgerBlock relation.');
        }

        $blockOut = [
            'height' => $block->height,
            'prev_commitment' => $block->prev_commitment,
            'sealed' => $block->sealed_at !== null,
        ];
        if ($block->sealed_at !== null) {
            $blockOut['merkle_root'] = $block->merkle_root;
            $blockOut['block_commitment'] = $block->block_commitment;
            $blockOut['operator_signature'] = $block->operator_signature;
            $blockOut['first_entry_id'] = $block->first_entry_id;
            $blockOut['last_entry_id'] = $block->last_entry_id;
            $blockOut['entry_count'] = $block->entry_count;
            $blockOut['sealed_at'] = $block->sealed_at?->toIso8601String();
        }

        return [
            'id' => $tx->id,
            'community_id' => $tx->community_id,
            'type' => $tx->type,
            'amount' => (string) $tx->amount,
            'from_public_ref' => $tx->from_public_ref,
            'to_public_ref' => $tx->to_public_ref,
            'actor_kind' => $tx->actor_kind,
            'note' => $tx->note,
            'leaf_hash' => $tx->leaf_hash,
            'created_at' => $tx->created_at?->toIso8601String(),
            'block' => $blockOut,
        ];
    }
}
