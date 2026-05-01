<?php

namespace App\Support\WalletLedger;

use App\Models\WalletLedgerBlock;

final class WalletLedgerTipPayload
{
    public static function latestSealedBlock(int $communityId): ?WalletLedgerBlock
    {
        return WalletLedgerBlock::query()
            ->where('community_id', $communityId)
            ->whereNotNull('sealed_at')
            ->orderByDesc('height')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function tipPayload(?WalletLedgerBlock $tip): ?array
    {
        if ($tip === null) {
            return null;
        }

        return [
            'height' => $tip->height,
            'prev_commitment' => $tip->prev_commitment,
            'merkle_root' => $tip->merkle_root,
            'block_commitment' => $tip->block_commitment,
            'operator_signature' => $tip->operator_signature,
            'first_entry_id' => $tip->first_entry_id,
            'last_entry_id' => $tip->last_entry_id,
            'entry_count' => $tip->entry_count,
            'sealed_at' => $tip->sealed_at?->toIso8601String(),
        ];
    }
}
