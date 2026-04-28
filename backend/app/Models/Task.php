<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'post_id',
        'folder_id',
        'assignee_id',
        'position',
        'completed_at',
        'highlighted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'highlighted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<ChatFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(ChatFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function scopeVisibleToUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('post', fn (Builder $q) => $q->visibleToUser($userId));
    }
}

