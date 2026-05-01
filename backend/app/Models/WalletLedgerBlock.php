<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletLedgerBlock extends Model
{
    protected $table = 'wallet_ledger_blocks';

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'height',
        'prev_commitment',
        'merkle_root',
        'first_entry_id',
        'last_entry_id',
        'entry_count',
        'block_commitment',
        'operator_signature',
        'sealed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'community_id' => 'integer',
            'height' => 'integer',
            'first_entry_id' => 'integer',
            'last_entry_id' => 'integer',
            'entry_count' => 'integer',
            'sealed_at' => 'datetime',
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
     * @return HasMany<WalletLedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class, 'wallet_ledger_block_id');
    }
}
