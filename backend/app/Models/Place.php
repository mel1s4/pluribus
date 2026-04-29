<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    public const ADMIN_ROLE_FULL_ACCESS = 'full_access';

    public const ADMIN_ROLE_EDITOR = 'editor';

    /** @var list<string> */
    public const ADMIN_ROLES = [
        self::ADMIN_ROLE_FULL_ACCESS,
        self::ADMIN_ROLE_EDITOR,
    ];

    public const LOCATION_NONE = 'none';

    public const LOCATION_POINT = 'point';

    /** @var list<string> */
    public const LOCATION_TYPES = [
        self::LOCATION_NONE,
        self::LOCATION_POINT,
    ];

    public const SERVICE_AREA_NONE = 'none';

    public const SERVICE_AREA_RADIUS = 'radius';

    public const SERVICE_AREA_POLYGON = 'polygon';

    /** @var list<string> */
    public const SERVICE_AREA_TYPES = [
        self::SERVICE_AREA_NONE,
        self::SERVICE_AREA_RADIUS,
        self::SERVICE_AREA_POLYGON,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'is_public',
        'description',
        'tags',
        'latitude',
        'longitude',
        'location_type',
        'service_area_type',
        'radius_meters',
        'area_geojson',
        'logo_path',
        'logo_background_color',
        'service_schedule',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'area_geojson' => 'array',
            'tags' => 'array',
            'service_schedule' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PlaceOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(PlaceOffer::class);
    }

    /**
     * @return HasMany<PlaceRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(PlaceRequirement::class);
    }

    /**
     * @return HasMany<PlaceAudience, $this>
     */
    public function audiences(): HasMany
    {
        return $this->hasMany(PlaceAudience::class);
    }

    /**
     * Delegated administrators (does not include the place owner).
     *
     * @return BelongsToMany<User, $this>
     */
    public function administrators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'place_administrators')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Role of the given user for this place: owner, full_access, editor, or empty string if none.
     */
    public function roleForUser(User $user): string
    {
        if ((int) $this->user_id === (int) $user->id) {
            return 'owner';
        }
        if ($this->relationLoaded('administrators')) {
            $hit = $this->administrators->firstWhere('id', $user->id);

            return is_string($hit?->pivot?->role) ? $hit->pivot->role : '';
        }

        $hit = $this->administrators()->where('users.id', $user->id)->first();

        return is_string($hit?->pivot?->role) ? $hit->pivot->role : '';
    }
}
