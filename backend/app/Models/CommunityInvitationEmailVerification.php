<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One-time email verification for completing join after an invitation link.
 * Plain tokens are hashed with SHA-256 like {@see CommunityInvitation}.
 */
class CommunityInvitationEmailVerification extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_invitation_id',
        'email',
        'token_hash',
        'expires_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(CommunityInvitation::class, 'community_invitation_id');
    }

    public static function hashPlainToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function isUsable(): bool
    {
        if ($this->consumed_at !== null) {
            return false;
        }
        if ($this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public static function findByPlainTokenForInvitation(int $invitationId, string $plain): ?self
    {
        $len = strlen($plain);
        if ($len < 16 || $len > 200) {
            return null;
        }

        return static::query()
            ->where('community_invitation_id', $invitationId)
            ->where('token_hash', self::hashPlainToken($plain))
            ->first();
    }
}
