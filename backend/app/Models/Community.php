<?php

namespace App\Models;

use App\Support\CommunityHost;
use App\Support\LocaleOptions;
use App\Support\PlaceMedia;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
     * Active community for this HTTP request (slug header, route, or custom host).
     */
    public static function forRequest(Request $request): self
    {
        $active = $request->attributes->get('active_community');
        if ($active instanceof self) {
            return $active;
        }

        $host = CommunityHost::normalize($request->getHost());
        if ($host !== null && ! CommunityHost::isPlatformHost($host)) {
            abort(403, 'Unknown community host.');
        }

        return self::current();
    }

    /**
     * Default community on platform hosts (first by id, created if missing).
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

    /**
     * @return HasMany<CommunityDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(CommunityDomain::class);
    }

    public function primaryDomain(): ?CommunityDomain
    {
        /** @var CommunityDomain|null $primary */
        $primary = $this->domains()->where('is_primary', true)->orderBy('id')->first();
        if ($primary !== null) {
            return $primary;
        }

        /** @var CommunityDomain|null $first */
        $first = $this->domains()->orderBy('id')->first();

        return $first;
    }

    public function publicSiteUrl(): ?string
    {
        $domain = $this->primaryDomain();
        if ($domain === null || $domain->host === '') {
            return null;
        }

        $scheme = app()->environment('production') ? 'https' : 'http';

        return $scheme.'://'.$domain->host;
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
