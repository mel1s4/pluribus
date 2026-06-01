<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityProject extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ARCHIVED = 'archived';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_ARCHIVED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'proposer_id',
        'title',
        'description',
        'status',
        'deadline',
        'has_budget',
        'has_job_positions',
        'latitude',
        'longitude',
        'location_type',
        'service_area_type',
        'radius_meters',
        'area_geojson',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'has_budget' => 'boolean',
            'has_job_positions' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'area_geojson' => 'array',
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
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    /**
     * @return HasMany<CommunityProjectBudgetItem, $this>
     */
    public function budgetItems(): HasMany
    {
        return $this->hasMany(CommunityProjectBudgetItem::class, 'community_project_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<CommunityProjectJobPosition, $this>
     */
    public function jobPositions(): HasMany
    {
        return $this->hasMany(CommunityProjectJobPosition::class, 'community_project_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
