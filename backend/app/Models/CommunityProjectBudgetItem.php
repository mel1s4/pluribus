<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityProjectBudgetItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'community_project_id',
        'name',
        'description',
        'cost',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CommunityProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'community_project_id');
    }
}
