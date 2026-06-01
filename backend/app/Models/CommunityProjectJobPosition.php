<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityProjectJobPosition extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'community_project_id',
        'title',
        'sort_order',
    ];

    /**
     * @return BelongsTo<CommunityProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'community_project_id');
    }

    /**
     * @return HasMany<CommunityProjectJobTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(CommunityProjectJobTask::class, 'job_position_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
