<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectArgument extends Model
{
    public const STANCE_PRO = 'pro';

    public const STANCE_CON = 'con';

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'parent_id',
        'stance',
        'title',
        'body',
        'author_id',
        'sort_order',
    ];

    /**
     * @return BelongsTo<CommunityProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'project_id');
    }

    /**
     * @return BelongsTo<ProjectArgument, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProjectArgument::class, 'parent_id');
    }

    /**
     * @return HasMany<ProjectArgument, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProjectArgument::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
