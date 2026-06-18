<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
    ];

    public const MAX_CUSTOM_OPTIONS_PER_SURVEY = 30;

    public const MAX_CUSTOM_LABELS_PER_BALLOT = 3;

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'author_id',
        'title',
        'description',
        'status',
        'closes_at',
        'allow_multiple',
        'require_ranked',
        'allow_add_options',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closes_at' => 'datetime',
            'allow_multiple' => 'boolean',
            'require_ranked' => 'boolean',
            'allow_add_options' => 'boolean',
        ];
    }

    public function normalizeModalityFlags(): void
    {
        if (! $this->allow_multiple) {
            $this->require_ranked = false;
        }
    }

    public function isOpen(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return false;
        }

        return true;
    }

    public function hasVotes(): bool
    {
        if ($this->relationLoaded('votes')) {
            return $this->votes->isNotEmpty();
        }

        return $this->votes()->exists();
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<SurveyOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(SurveyOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<SurveyVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(SurveyVote::class);
    }
}
