<?php

namespace App\Support\WalletLedger;

use App\Models\WalletLedgerEntry;
use App\Support\WalletMoney;

/**
 * Canonical UTF-8 string for hashing; versioned so auditors can reimplement.
 *
 * Format (pipe-separated, no trailing newline):
 * wallet_ledger_entry_v1|community_id|entry_id|type|amount|from_ref|to_ref|actor_kind|note|created_at_iso8601
 */
final class CanonicalLedgerEntry
{
    public static function stringFor(WalletLedgerEntry $entry): string
    {
        $amount = WalletMoney::normalize((string) $entry->amount);
        $from = $entry->from_public_ref ?? '';
        $note = $entry->note ?? '';
        $created = $entry->created_at?->clone()->utc()->format('Y-m-d\TH:i:s\Z') ?? '';

        return implode('|', [
            'wallet_ledger_entry_v1',
            (string) $entry->community_id,
            (string) $entry->id,
            (string) $entry->type,
            $amount,
            $from,
            (string) $entry->to_public_ref,
            (string) $entry->actor_kind,
            $note,
            $created,
        ]);
    }

    public static function leafHashHex(WalletLedgerEntry $entry): string
    {
        return hash('sha256', self::stringFor($entry), false);
    }
}
