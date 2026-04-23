<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'username',
    'avatar_path',
    'phone_numbers',
    'contact_emails',
    'aliases',
    'external_links',
    'password',
    'is_root',
    'user_type',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** Stored on users who are not root; root accounts use {@see $is_root}. */
    public const USER_TYPES = ['root', 'admin', 'member', 'developer'];

    /** Values root may assign via {@see users.assign_types} (excludes root). */
    public const ASSIGNABLE_USER_TYPES = ['admin', 'member', 'developer'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
}
