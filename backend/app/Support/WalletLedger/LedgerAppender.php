<?php

namespace App\Support\WalletLedger;

use App\Models\WalletLedgerBlock;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Append-only ledger entries grouped into blocks; seals when entry count reaches config.
 * Call from within an outer DB transaction (e.g. wallet transfer) with locks held.
 */
final class LedgerAppender
{
    public function __construct(private LedgerSigner $signer) {}

    /**
     * @return array{0: WalletLedgerEntry, 1: bool} Entry and whether the block was sealed by this append
     */
    public function append(
        int $communityId,
        string $type,
        string $amount,
        ?string $fromPublicRef,
        string $toPublicRef,
        string $actorKind,
        ?string $note,
    ): array {
        $threshold = max(1, (int) config('wallet_ledger.entries_per_block', 1));

        $tip = WalletLedgerBlock::query()
            ->where('community_id', $communityId)
            ->orderByDesc('height')
            ->lockForUpdate()
            ->first();

        $block = $this->resolveOpenBlock($communityId, $tip);
        $position = (int) WalletLedgerEntry::query()
            ->where('wallet_ledger_block_id', $block->id)
            ->count();

        $entry = WalletLedgerEntry::query()->create([
            'community_id' => $communityId,
            'wallet_ledger_block_id' => $block->id,
            'position_in_block' => $position,
            'type' => $type,
            'amount' => $amount,
            'from_public_ref' => $fromPublicRef,
            'to_public_ref' => $toPublicRef,
            'actor_kind' => $actorKind,
            'note' => $note,
            'leaf_hash' => '',
        ]);

        $entry->refresh();
        $entry->leaf_hash = CanonicalLedgerEntry::leafHashHex($entry);
        $entry->save();

        $countAfter = $position + 1;
        $block->entry_count = $countAfter;
        $block->save();

        $sealed = false;
        if ($countAfter >= $threshold) {
            $this->sealBlock($block->fresh());
            $sealed = true;
        }

        return [$entry->fresh(), $sealed];
    }

    /**
     * Seal the community's current open block if it has at least one entry and is not yet sealed.
     * Used by artisan when entries_per_block > 1 to finalize a partial tail block.
     */
    public function sealOpenBlockIfNeeded(int $communityId): bool
    {
        return (bool) DB::transaction(function () use ($communityId): bool {
            $open = WalletLedgerBlock::query()
                ->where('community_id', $communityId)
                ->whereNull('sealed_at')
                ->orderByDesc('height')
                ->lockForUpdate()
                ->first();

            if ($open === null) {
                return false;
            }

            $n = (int) WalletLedgerEntry::query()
                ->where('wallet_ledger_block_id', $open->id)
                ->count();

            if ($n === 0) {
                return false;
            }

            $this->sealBlock($open->fresh());

            return true;
        });
    }

    private function resolveOpenBlock(int $communityId, ?WalletLedgerBlock $tip): WalletLedgerBlock
    {
        if ($tip === null) {
            return WalletLedgerBlock::query()->create([
                'community_id' => $communityId,
                'height' => 0,
                'prev_commitment' => Genesis::PREV_COMMITMENT_HEX,
                'entry_count' => 0,
            ]);
        }

        if ($tip->sealed_at !== null) {
            if ($tip->block_commitment === null) {
                throw new \RuntimeException('Sealed block missing commitment.');
            }

            return WalletLedgerBlock::query()->create([
                'community_id' => $communityId,
                'height' => (int) $tip->height + 1,
                'prev_commitment' => $tip->block_commitment,
                'entry_count' => 0,
            ]);
        }

        return $tip;
    }

    private function sealBlock(WalletLedgerBlock $block): void
    {
        if ($block->sealed_at !== null) {
            return;
        }

        $entries = WalletLedgerEntry::query()
            ->where('wallet_ledger_block_id', $block->id)
            ->orderBy('position_in_block')
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        $leafHexes = [];
        foreach ($entries as $e) {
            $leafHexes[] = $e->leaf_hash;
        }

        $merkleRoot = MerkleTree::rootHex($leafHexes);
        $firstId = (int) $entries->first()->id;
        $lastId = (int) $entries->last()->id;

        $commitment = BlockHasher::commitmentHex(
            (int) $block->community_id,
            (int) $block->height,
            $block->prev_commitment,
            $merkleRoot,
            $firstId,
            $lastId,
        );

        $signature = $this->signer->signCommitmentHex($commitment);

        $block->merkle_root = $merkleRoot;
        $block->first_entry_id = $firstId;
        $block->last_entry_id = $lastId;
        $block->block_commitment = $commitment;
        $block->operator_signature = base64_encode($signature);
        $block->sealed_at = now();
        $block->save();
    }
}
