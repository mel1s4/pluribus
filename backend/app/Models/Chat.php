<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    public const TYPE_DIRECT = 'direct';

    public const TYPE_GROUP = 'group';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_DIRECT,
        self::TYPE_GROUP,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'owner_id',
        'type',
        'title',
        'icon_emoji',
        'icon_bg_color',
        'folder_id',
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
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_members')
            ->withPivot(['joined_at', 'last_read_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * @return HasMany<ChatBackup, $this>
     */
    public function backups(): HasMany
    {
        return $this->hasMany(ChatBackup::class);
    }

    public function scopeVisibleToUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('members', fn (Builder $q) => $q->where('users.id', $userId));
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->id;
    }
}
