<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation tokens are stored as SHA-256 hashes of the secret shown in join URLs.
 */
class CommunityInvitation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'created_by',
        'token_hash',
        'email',
        'max_uses',
        'uses_count',
        'expires_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function hashPlainToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function findByPlainToken(string $plain): ?self
    {
        $len = strlen($plain);
        if ($len < 16 || $len > 200) {
            return null;
        }

        return static::query()->where('token_hash', self::hashPlainToken($plain))->first();
    }

    /**
     * Machine-readable reason this invitation cannot be used, or null if it can.
     */
    public function failureReason(): ?string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }
        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return 'exhausted';
        }

        return null;
    }

    public function isUsable(): bool
    {
        return $this->failureReason() === null;
    }

    /**
     * Remaining allowed joins, or null when unlimited (subject to expiry).
     */
    public function usesRemaining(): ?int
    {
        if ($this->max_uses === null) {
            return null;
        }

        return max(0, (int) $this->max_uses - (int) $this->uses_count);
    }
}
