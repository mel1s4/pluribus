<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public const TYPE_USER = 'user';

    public const TYPE_SYSTEM = 'system';

    public const EVENT_MEMBER_ADDED = 'member_added';

    public const EVENT_MEMBER_REMOVED = 'member_removed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'chat_id',
        'user_id',
        'type',
        'event_key',
        'event_meta',
        'body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'event_meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Chat, $this>
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
