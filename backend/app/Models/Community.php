<?php

namespace App\Models;

use App\Support\LocaleOptions;
use Illuminate\Database\Eloquent\Model;

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
        'description',
        'rules',
        'logo',
        'default_language',
    ];

    /**
     * Singleton row: first by id, created if missing (e.g. fresh DB before seeder).
     */
    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();
        if ($row !== null) {
            return $row;
        }

        return static::query()->create([
            'name' => 'Community',
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
        ]);
    }
}
