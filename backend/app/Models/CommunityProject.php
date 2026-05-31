<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityProject extends Model
{
    public const STATUS_OPEN = 'open';

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'proposer_id',
        'title',
        'description',
        'thesis_title',
        'thesis_body',
        'status',
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
     * @return HasMany<ProjectArgument, $this>
     */
    public function arguments(): HasMany
    {
        return $this->hasMany(ProjectArgument::class, 'project_id');
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
}
