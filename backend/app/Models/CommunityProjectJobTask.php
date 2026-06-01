<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityProjectJobTask extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'job_position_id',
        'body',
        'sort_order',
    ];

    /**
     * @return BelongsTo<CommunityProjectJobPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(CommunityProjectJobPosition::class, 'job_position_id');
    }
}
