<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Append-only ledger row; leaf_hash binds payload for Merkle inclusion in {@see WalletLedgerBlock}.
 */
class WalletLedgerEntry extends Model
{
    public const TYPE_GRANT = 'grant';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_ORDER_SETTLEMENT = 'order_settlement';

    public const ACTOR_COMMUNITY_GRANT = 'community_grant';

    public const ACTOR_MEMBER_TRANSFER = 'member_transfer';

    public const ACTOR_ORDER_CHECKOUT = 'order_checkout';

    public const ACTOR_PLACE_TREASURY_TRANSFER = 'place_treasury_transfer';

    protected $table = 'wallet_ledger_entries';

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'wallet_ledger_block_id',
        'position_in_block',
        'type',
        'amount',
        'from_public_ref',
        'to_public_ref',
        'actor_kind',
        'note',
        'leaf_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'community_id' => 'integer',
            'wallet_ledger_block_id' => 'integer',
            'position_in_block' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsTo<WalletLedgerBlock, $this>
     */
    public function ledgerBlock(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerBlock::class, 'wallet_ledger_block_id');
    }

    /**
     * @return HasOne<WalletPrivilegedAudit, $this>
     */
    public function privilegedAudit(): HasOne
    {
        return $this->hasOne(WalletPrivilegedAudit::class);
    }
}
