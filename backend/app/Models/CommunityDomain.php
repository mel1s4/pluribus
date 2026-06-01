<?php

namespace App\Models;

use App\Support\CommunityHost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityDomain extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'host',
        'is_primary',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'community_id' => 'integer',
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public static function normalizeHost(string $host): string
    {
        return CommunityHost::normalize($host) ?? '';
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
