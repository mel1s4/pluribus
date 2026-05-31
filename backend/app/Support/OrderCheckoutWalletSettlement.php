<?php

namespace App\Support;

use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Models\User;
use App\Support\WalletLedger\LedgerAppender;
use Illuminate\Validation\ValidationException;

/**
 * Debits the buyer once and credits each place wallet; appends one ledger entry per place leg.
 * Must run inside an outer database transaction with row locks (same as order creation).
 */
final class OrderCheckoutWalletSettlement
{
    public function __construct(private LedgerAppender $ledgerAppender) {}

    /**
     * @param  array<int, string>  $placeIdToSubtotal  place_id => normalized decimal string
     */
    public function settle(
        User $buyer,
        int $communityId,
        string $totalAmount,
        array $placeIdToSubtotal,
        ?string $ledgerNote = null,
    ): void {
        $totalAmount = WalletMoney::normalize($totalAmount);
        if (! WalletMoney::isPositive($totalAmount)) {
            throw ValidationException::withMessages([
                'cart' => [__('Order total must be positive.')],
            ]);
        }

        $sum = '0.00';
        foreach ($placeIdToSubtotal as $pid => $sub) {
            $sub = WalletMoney::normalize((string) $sub);
            if (! WalletMoney::isPositive($sub)) {
                throw ValidationException::withMessages([
                    'cart' => [__('Invalid cart totals.')],
                ]);
            }
            $sum = WalletMoney::add($sum, $sub);
        }

        if (WalletMoney::compare($sum, $totalAmount) !== 0) {
            throw ValidationException::withMessages([
                'cart' => [__('Cart totals do not match order total.')],
            ]);
        }

        $buyerWallet = Wallet::firstOrCreateForMember($communityId, (int) $buyer->id);
        /** @var array<int, Wallet> */
        $placeWallets = [];
        foreach (array_keys($placeIdToSubtotal) as $placeId) {
            $placeWallets[(int) $placeId] = Wallet::firstOrCreateForPlace($communityId, (int) $placeId);
        }

        $ids = array_map(fn (Wallet $w): int => (int) $w->id, array_merge([$buyerWallet], array_values($placeWallets)));
        sort($ids);

        foreach ($ids as $wid) {
            Wallet::query()->whereKey($wid)->lockForUpdate()->firstOrFail();
        }

        $buyerRow = Wallet::query()->whereKey($buyerWallet->id)->firstOrFail();
        if (WalletMoney::compare((string) $buyerRow->balance, $totalAmount) < 0) {
            throw ValidationException::withMessages([
                'cart' => [__('Insufficient wallet balance for this order.')],
            ]);
        }

        $buyerRow->balance = WalletMoney::sub((string) $buyerRow->balance, $totalAmount);
        $buyerRow->save();

        foreach ($placeIdToSubtotal as $placeId => $sub) {
            $sub = WalletMoney::normalize((string) $sub);
            $pw = Wallet::query()->whereKey($placeWallets[(int) $placeId]->id)->firstOrFail();
            $pw->balance = WalletMoney::add((string) $pw->balance, $sub);
            $pw->save();

            $this->ledgerAppender->append(
                $communityId,
                WalletLedgerEntry::TYPE_ORDER_SETTLEMENT,
                $sub,
                $buyerRow->public_ref,
                $pw->public_ref,
                WalletLedgerEntry::ACTOR_ORDER_CHECKOUT,
                $ledgerNote,
            );
        }
    }
}
