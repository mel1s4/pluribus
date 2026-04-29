<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';

    /** @var list<string> */
    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_ADMIN,
        self::ROLE_MEMBER,
    ];

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'owner_id',
        'name',
        'description',
    ];

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
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Calendar, $this>
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'shared_group_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'shared_group_id');
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function sharedFolders(): HasMany
    {
        return $this->hasMany(Folder::class, 'shared_group_id');
    }

    public function scopeVisibleToUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('owner_id', $userId)
                ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $userId));
        });
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->id;
    }

    public function hasMember(int $userId): bool
    {
        return $this->members()->where('users.id', $userId)->exists();
    }
}

