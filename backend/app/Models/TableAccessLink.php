<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableAccessLink extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'table_id',
        'created_by',
        'token_hash',
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
     * @return BelongsTo<Table, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }
}
