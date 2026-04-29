<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaceOffer extends Model
{
    public const VISIBILITY_SCOPE_PUBLIC = 'public';

    public const VISIBILITY_SCOPE_AUDIENCE = 'audience';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'place_id',
        'sku',
        'title',
        'description',
        'price',
        'photo_path',
        'gallery_paths',
        'tags',
        'visibility_scope',
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
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * @return BelongsToMany<PlaceAudience, $this>
     */
    public function audiences(): BelongsToMany
    {
        return $this->belongsToMany(PlaceAudience::class, 'place_offer_audience');
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'place_offer_id');
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
