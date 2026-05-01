<?php

namespace App\Models;

use App\Support\LocaleOptions;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Application-wide community (exactly one row). Use {@see self::current()} to resolve it.
 */
class Community extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'rules',
        'logo',
        'default_language',
        'currency_code',
        'latitude',
        'longitude',
    ];

    /**
     * Singleton row: first by id, created if missing (e.g. fresh DB before seeder).
     */
    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();
        if ($row !== null) {
            if ($row->slug === null || $row->slug === '') {
                $row->slug = static::slugFromName($row->name ?? 'community');
                $row->save();
            }
            return $row;
        }

        return static::query()->create([
            'name' => 'Community',
            'slug' => 'community',
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
        ]);
    }

    public static function slugFromName(string $name): string
    {
        $slug = Str::slug($name);
        if ($slug !== '') {
            return Str::limit($slug, 64, '');
        }

        return Str::limit(Str::lower(Str::replace('-', '', (string) Str::uuid())), 64, '');
    }

    /**
     * @return BelongsToMany<User, $this, CommunityMembership>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_user')
            ->using(CommunityMembership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Wallet, $this>
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }
}
