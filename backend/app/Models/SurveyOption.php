<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyOption extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'survey_id',
        'label',
        'sort_order',
        'is_custom',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_custom' => 'boolean',
        ];
    }

    public static function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim($label));
    }

    /**
     * @return BelongsTo<Survey, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * @return HasMany<SurveyVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(SurveyVote::class);
    }
}
