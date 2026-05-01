<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Wallet extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'user_id',
        'balance',
        'public_ref',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'community_id' => 'integer',
            'user_id' => 'integer',
            'balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Wallet $wallet): void {
            if ($wallet->public_ref === null || $wallet->public_ref === '') {
                $wallet->public_ref = (string) Str::ulid();
            }
        });
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function firstOrCreateForMember(int $communityId, int $userId): self
    {
        return static::query()->firstOrCreate(
            [
                'community_id' => $communityId,
                'user_id' => $userId,
            ],
            [
                'balance' => '0.00',
            ]
        );
    }
}
