<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceOffer extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'place_id',
        'title',
        'description',
        'price',
        'photo_path',
        'gallery_paths',
        'tags',
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
}
