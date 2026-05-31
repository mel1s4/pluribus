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
        'place_id',
        'balance',
        'public_ref',
        'wallet_owner_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'community_id' => 'integer',
            'user_id' => 'integer',
            'place_id' => 'integer',
            'balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Wallet $wallet): void {
            if ($wallet->public_ref === null || $wallet->public_ref === '') {
                $wallet->public_ref = (string) Str::ulid();
            }
            if ($wallet->wallet_owner_key === null || $wallet->wallet_owner_key === '') {
                if ($wallet->place_id !== null) {
                    $wallet->wallet_owner_key = self::ownerKeyForPlace((int) $wallet->place_id);
                } elseif ($wallet->user_id !== null) {
                    $wallet->wallet_owner_key = self::ownerKeyForUser((int) $wallet->user_id);
                }
            }
        });
    }

    public static function ownerKeyForUser(int $userId): string
    {
        return 'u:'.$userId;
    }

    public static function ownerKeyForPlace(int $placeId): string
    {
        return 'p:'.$placeId;
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

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public static function firstOrCreateForMember(int $communityId, int $userId): self
    {
        return static::query()->firstOrCreate(
            [
                'community_id' => $communityId,
                'wallet_owner_key' => self::ownerKeyForUser($userId),
            ],
            [
                'user_id' => $userId,
                'place_id' => null,
                'balance' => '0.00',
            ]
        );
    }

    public static function firstOrCreateForPlace(int $communityId, int $placeId): self
    {
        return static::query()->firstOrCreate(
            [
                'community_id' => $communityId,
                'wallet_owner_key' => self::ownerKeyForPlace($placeId),
            ],
            [
                'place_id' => $placeId,
                'user_id' => null,
                'balance' => '0.00',
            ]
        );
    }
}
