<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteEditLock extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'note_id',
        'user_id',
        'session_id',
        'locked_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locked_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Note, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->locked_until->isPast();
    }
}
