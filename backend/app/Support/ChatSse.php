<?php

namespace App\Support;

final class ChatSse
{
    public static function userChannel(int $userId): string
    {
        return 'sse:user:'.$userId;
    }
}
