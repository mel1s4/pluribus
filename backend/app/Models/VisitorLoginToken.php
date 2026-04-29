<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorLoginToken extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
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

    public static function hashPlainToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
