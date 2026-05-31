<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Community;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Collection;

trait WalletLedgerOwnerFormatting
{
    /**
     * @return array{name: string|null, code: string|null}
     */
    protected function currencyDisplayForCommunity(?Community $community): array
    {
        if ($community === null) {
            return ['name' => null, 'code' => null];
        }

        $name = $community->currency_name;
        $code = $community->currency_code;

        return [
            'name' => is_string($name) && $name !== '' ? $name : null,
            'code' => is_string($code) && $code !== '' ? $code : null,
        ];
    }

    /**
     * @return array<string, Wallet>
     */
    protected function loadWalletsByPublicRefs(int $communityId, array $refs): array
    {
        if ($refs === []) {
            return [];
        }

        $wallets = Wallet::query()
            ->where('community_id', $communityId)
            ->whereIn('public_ref', $refs)
            ->with(['user:id,name,email', 'place:id,name'])
            ->get();

        $map = [];
        foreach ($wallets as $w) {
            $map[$w->public_ref] = $w;
        }

        return $map;
    }

    /**
     * @param  Collection<int, WalletLedgerEntry>  $collection
     * @return list<string>
     */
    protected function collectPublicRefsFromPage($collection): array
    {
        $refs = [];
        foreach ($collection as $tx) {
            if (is_string($tx->from_public_ref) && $tx->from_public_ref !== '') {
                $refs[$tx->from_public_ref] = true;
            }
            if (is_string($tx->to_public_ref) && $tx->to_public_ref !== '') {
                $refs[$tx->to_public_ref] = true;
            }
        }

        return array_keys($refs);
    }

    /**
     * @param  array<string, Wallet>  $byRef
     * @return array<string, mixed>
     */
    protected function formatOwnerTransaction(WalletLedgerEntry $tx, Wallet $myWallet, array $byRef): array
    {
        $myRef = $myWallet->public_ref;
        $incoming = $tx->to_public_ref === $myRef;
        $counterpartyRef = $incoming ? $tx->from_public_ref : $tx->to_public_ref;
        $counterpartyLabel = __('Community');
        $counterpartyMaskedEmail = null;

        if ($tx->type === WalletLedgerEntry::TYPE_TRANSFER && is_string($counterpartyRef) && $counterpartyRef !== '') {
            $other = $byRef[$counterpartyRef] ?? null;
            if ($other !== null && $other->user !== null) {
                $counterpartyLabel = $other->user->name;
                $counterpartyMaskedEmail = $this->maskEmail($other->user->email);
            } elseif ($other !== null && $other->place !== null) {
                $counterpartyLabel = (string) $other->place->name;
            } else {
                $counterpartyLabel = __('Member');
            }
        } elseif ($tx->type === WalletLedgerEntry::TYPE_ORDER_SETTLEMENT && is_string($counterpartyRef) && $counterpartyRef !== '') {
            $other = $byRef[$counterpartyRef] ?? null;
            if ($other !== null && $other->user !== null) {
                $counterpartyLabel = $other->user->name;
                $counterpartyMaskedEmail = $this->maskEmail($other->user->email);
            } elseif ($other !== null && $other->place !== null) {
                $counterpartyLabel = (string) $other->place->name;
            } else {
                $counterpartyLabel = $incoming ? __('Customer') : __('Place');
            }
        } elseif ($tx->type === WalletLedgerEntry::TYPE_GRANT) {
            $counterpartyLabel = __('Community grant');
        }

        return [
            'id' => $tx->id,
            'type' => $tx->type,
            'direction' => $incoming ? 'in' : 'out',
            'amount' => (string) $tx->amount,
            'counterparty_label' => $counterpartyLabel,
            'counterparty_masked_email' => $counterpartyMaskedEmail,
            'note' => $tx->note,
            'created_at' => $tx->created_at?->toIso8601String(),
        ];
    }

    protected function maskEmail(?string $email): string
    {
        if ($email === null || $email === '') {
            return '';
        }
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $first = $local !== '' ? mb_substr($local, 0, 1) : '?';

        return $first.'***@'.$domain;
    }
}
