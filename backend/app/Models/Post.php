<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Post extends Model
{
    public const TYPE_TASK = 'task';
    public const TYPE_EVENT = 'event';
    public const TYPE_ANNOUNCEMENT = 'announcement';
    public const TYPE_INFO = 'info';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_TASK,
        self::TYPE_EVENT,
        self::TYPE_ANNOUNCEMENT,
        self::TYPE_INFO,
    ];

    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_COMMUNITY = 'community';
    public const VISIBILITY_GROUP = 'group';

    /** @var list<string> */
    public const VISIBILITY_SCOPES = [
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_COMMUNITY,
        self::VISIBILITY_GROUP,
    ];

    public const INFLUENCE_NONE = 'none';
    public const INFLUENCE_RADIUS = 'radius';
    public const INFLUENCE_POLYGON = 'polygon';

    /** @var list<string> */
    public const INFLUENCE_AREA_TYPES = [
        self::INFLUENCE_NONE,
        self::INFLUENCE_RADIUS,
        self::INFLUENCE_POLYGON,
    ];

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'author_id',
        'shared_group_id',
        'calendar_id',
        'place_id',
        'type',
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
        'influence_area_type',
        'influence_radius_meters',
        'influence_area_geojson',
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
            'influence_radius_meters' => 'integer',
            'influence_area_geojson' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
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
     * @return HasOne<Task, $this>
     */
    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }

    public function scopeVisibleToUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('author_id', $userId)
                ->orWhere('visibility_scope', self::VISIBILITY_COMMUNITY)
                ->orWhere(function (Builder $g) use ($userId): void {
                    $g->where('visibility_scope', self::VISIBILITY_GROUP)
                        ->whereNotNull('shared_group_id')
                        ->whereHas('sharedGroup.members', fn (Builder $m) => $m->where('users.id', $userId));
                });
        });
    }
}

