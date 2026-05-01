<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletPrivilegedAudit extends Model
{
    protected $table = 'wallet_privileged_audits';

    /** @var list<string> */
    protected $fillable = [
        'wallet_ledger_entry_id',
        'actor_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wallet_ledger_entry_id' => 'integer',
            'actor_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WalletLedgerEntry, $this>
     */
    public function walletLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
