<?php

namespace App\Models;

use App\Support\LocaleOptions;
use App\Support\PlaceMedia;
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
        'terms_markdown',
        'privacy_policy_markdown',
        'logo',
        'default_language',
        'currency_code',
        'currency_name',
        'local_currency_code',
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

    /**
     * @return HasMany<CommunityProject, $this>
     */
    public function communityProjects(): HasMany
    {
        return $this->hasMany(CommunityProject::class);
    }

    public function publicLogoUrl(): ?string
    {
        $logo = $this->logo;
        if ($logo === null || $logo === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $logo) === 1) {
            return $logo;
        }

        return PlaceMedia::publicUrl($logo);
    }
}
