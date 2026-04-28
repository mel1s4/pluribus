<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatFolder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'shared_group_id',
        'name',
        'icon_emoji',
        'icon_bg_color',
        'parent_id',
        'sort_order',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function sharedGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'shared_group_id');
    }

    /**
     * @return BelongsTo<ChatFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ChatFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Chat, $this>
     */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class, 'folder_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'folder_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeVisibleToUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('user_id', $userId)
                ->orWhere(function (Builder $g) use ($userId): void {
                    $g->whereNotNull('shared_group_id')
                        ->whereHas('sharedGroup.members', fn (Builder $m) => $m->where('users.id', $userId));
                });
        });
    }
}
