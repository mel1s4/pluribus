<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteRevision extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'note_id',
        'edited_by_user_id',
        'title',
        'description',
        'tags',
        'content_markdown',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'created_at' => 'datetime',
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
    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }
}
