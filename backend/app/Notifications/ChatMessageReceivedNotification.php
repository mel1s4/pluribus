<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChatMessageReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $chatId,
        public string $chatTitle,
        public int $messageId,
        public string $senderName,
        public string $preview,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'chat_message',
            'title' => $this->chatTitle !== '' ? $this->chatTitle : __('New message'),
            'body' => __(':sender: :preview', [
                'sender' => $this->senderName,
                'preview' => $this->preview,
            ]),
            'action' => [
                'type' => 'chat',
                'chat_id' => $this->chatId,
            ],
        ];
    }
}
