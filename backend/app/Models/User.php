<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'username',
        'profile_slug',
        'avatar_path',
        'phone_numbers',
        'contact_emails',
        'aliases',
        'external_links',
        'password',
        'is_root',
        'user_type',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** Stored on users who are not root; root accounts use {@see $is_root}. */
    public const USER_TYPES = ['root', 'admin', 'member', 'developer', 'visitor'];

    /** Values root may assign via {@see users.assign_types} (excludes root). */
    public const ASSIGNABLE_USER_TYPES = ['admin', 'member', 'developer', 'visitor'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->username) && empty($user->profile_slug)) {
                $user->profile_slug = (string) Str::uuid();
            }
        });

        static::updating(function (User $user): void {
            if (empty($user->username) && empty($user->profile_slug)) {
                $user->profile_slug = (string) Str::uuid();
            }
        });
    }

    /**
     * Resolve member profile routes: username, stable profile_slug, or numeric id.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $user = static::query()->where('username', $value)->first();
        if ($user !== null) {
            return $user;
        }

        $user = static::query()->where('profile_slug', $value)->first();
        if ($user !== null) {
            return $user;
        }

        return static::query()->where('id', $value)->firstOrFail();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_root' => 'boolean',
            'phone_numbers' => 'array',
            'contact_emails' => 'array',
            'aliases' => 'array',
            'external_links' => 'array',
        ];
    }

    public function isRoot(): bool
    {
        return $this->is_root === true;
    }

    /**
     * @return HasMany<Place, $this>
     */
    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    /**
     * @return HasMany<Chat, $this>
     */
    public function ownedChats(): HasMany
    {
        return $this->hasMany(Chat::class, 'owner_id');
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    /**
     * @return HasMany<Calendar, $this>
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'owner_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    /**
     * @return HasMany<UserFavorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderByDesc('created_at');
    }
}
