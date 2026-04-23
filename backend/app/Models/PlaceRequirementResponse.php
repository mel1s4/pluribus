<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceRequirementResponse extends Model
{
    public const VISIBILITY_CREATOR_ONLY = 'creator_only';

    public const VISIBILITY_COMMUNITY = 'community';

    /** @var list<string> */
    public const VISIBILITIES = [
        self::VISIBILITY_CREATOR_ONLY,
        self::VISIBILITY_COMMUNITY,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'place_requirement_id',
        'user_id',
        'title',
        'description',
        'price',
        'photo_path',
        'gallery_paths',
        'tags',
        'visibility',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'gallery_paths' => 'array',
            'tags' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PlaceRequirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(PlaceRequirement::class, 'place_requirement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
