<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
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
        'author_id',
        'shared_group_id',
        'calendar_id',
        'place_id',
        'folder_id',
        'assignee_id',
        'title',
        'description',
        'content_markdown',
        'tags',
        'start_at',
        'end_at',
        'all_day',
        'recurrence_rule',
        'recurrence_id',
        'latitude',
        'longitude',
        'position',
        'completed_at',
        'highlighted',
        'visibility_scope',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'completed_at' => 'datetime',
            'highlighted' => 'boolean',
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function sharedGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'shared_group_id');
    }

    /**
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
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
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('author_id', $userId)
                ->orWhere('assignee_id', $userId)
                ->orWhere('visibility_scope', self::VISIBILITY_COMMUNITY)
                ->orWhere(function (Builder $g) use ($userId): void {
                    $g->where('visibility_scope', self::VISIBILITY_GROUP)
                        ->whereNotNull('shared_group_id')
                        ->whereHas('sharedGroup.members', fn (Builder $m) => $m->where('users.id', $userId));
                });
        });
    }
}

