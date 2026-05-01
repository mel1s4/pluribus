<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVotingIdAudit extends Model
{
    /** @var string|null */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'changed_by_user_id',
        'old_voting_id',
        'new_voting_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
