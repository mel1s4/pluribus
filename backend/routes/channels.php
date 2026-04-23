<?php

use App\Models\Chat;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function ($user, int $chatId): bool {
    return Chat::query()
        ->whereKey($chatId)
        ->whereHas('members', fn ($query) => $query->where('users.id', $user->id))
        ->exists();
});
