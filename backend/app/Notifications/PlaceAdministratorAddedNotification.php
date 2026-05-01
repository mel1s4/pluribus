<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlaceAdministratorAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $placeId,
        public string $placeName,
        public string $inviterName,
        public string $role,
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
            'kind' => 'place_admin_added',
            'title' => __('Place team access'),
            'body' => __(':inviter added you to :place as :role.', [
                'inviter' => $this->inviterName,
                'place' => $this->placeName,
                'role' => $this->role,
            ]),
            'action' => [
                'type' => 'place',
                'place_id' => $this->placeId,
            ],
        ];
    }
}
