<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatSseStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_chat_sse_stream(): void
    {
        $this->get('/api/chats/stream')
            ->assertUnauthorized();
    }
}
