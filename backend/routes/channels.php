<?php

use App\Models\Chat;
use App\Models\Place;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function ($user, int $chatId): bool {
    return Chat::query()
        ->whereKey($chatId)
        ->whereHas('members', fn ($query) => $query->where('users.id', $user->id))
        ->exists();
});

Broadcast::channel('place.{placeId}', function ($user, int $placeId): bool {
    $place = Place::query()->find($placeId);

    return $place !== null && $user->can('update', $place);
});
