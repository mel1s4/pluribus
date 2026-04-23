<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChatBackup extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'chat_id',
        'user_id',
        'file_path',
    ];

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

    public function downloadUrl(): string
    {
        return Storage::disk('local')->url($this->file_path);
    }
}
