<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calendar extends Model
{
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_COMMUNITY = 'community';
    public const VISIBILITY_GROUP = 'group';

    /** @var list<string> */
    public const VISIBILITY_SCOPES = [
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_COMMUNITY,
        self::VISIBILITY_GROUP,
    ];

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'owner_id',
        'shared_group_id',
        'name',
        'color',
        'visibility_scope',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function sharedGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'shared_group_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function scopeVisibleToUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('owner_id', $userId)
                ->orWhere('visibility_scope', self::VISIBILITY_COMMUNITY)
                ->orWhere(function (Builder $g) use ($userId): void {
                    $g->where('visibility_scope', self::VISIBILITY_GROUP)
                        ->whereNotNull('shared_group_id')
                        ->whereHas('sharedGroup.members', fn (Builder $m) => $m->where('users.id', $userId));
                });
        });
    }
}

