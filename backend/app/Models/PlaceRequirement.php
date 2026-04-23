<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PlaceRequirement extends Model
{
    public const VISIBILITY_SCOPE_PUBLIC = 'public';

    public const VISIBILITY_SCOPE_AUDIENCE = 'audience';

    public const RECURRENCE_ONCE = 'once';

    public const RECURRENCE_WEEKLY = 'weekly';

    /** @var list<string> */
    public const RECURRENCE_MODES = [
        self::RECURRENCE_ONCE,
        self::RECURRENCE_WEEKLY,
    ];

    /** @var list<string> */
    public const WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'place_id',
        'title',
        'description',
        'quantity',
        'unit',
        'recurrence_mode',
        'recurrence_weekdays',
        'photo_path',
        'gallery_paths',
        'tags',
        'example_place_offer_id',
        'visibility_scope',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'gallery_paths' => 'array',
            'recurrence_weekdays' => 'array',
            'tags' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * @return BelongsTo<PlaceOffer, $this>
     */
    public function exampleOffer(): BelongsTo
    {
        return $this->belongsTo(PlaceOffer::class, 'example_place_offer_id');
    }

    /**
     * @return HasMany<PlaceRequirementResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(PlaceRequirementResponse::class, 'place_requirement_id');
    }

    /**
     * @return BelongsToMany<PlaceAudience, $this>
     */
    public function audiences(): BelongsToMany
    {
        return $this->belongsToMany(PlaceAudience::class, 'place_requirement_audience');
    }

    public function scopeVisibleToUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $inner) use ($userId): void {
            $inner
                ->where('visibility_scope', self::VISIBILITY_SCOPE_PUBLIC)
                ->orWhere(function (Builder $scoped) use ($userId): void {
                    $scoped
                        ->where('visibility_scope', self::VISIBILITY_SCOPE_AUDIENCE)
                        ->whereHas('audiences.members', fn (Builder $m) => $m->where('users.id', $userId));
                });
        });
    }
}
