<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'place_id',
        'name',
    ];

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * @return HasMany<TableAccessLink, $this>
     */
    public function accessLinks(): HasMany
    {
        return $this->hasMany(TableAccessLink::class);
    }
}
