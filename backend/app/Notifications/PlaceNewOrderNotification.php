<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlaceNewOrderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $placeId,
        public string $placeName,
        public int $orderId,
        public string $orderNumber,
        public string $placeSubtotal,
        public string $customerName,
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
            'kind' => 'place_new_order',
            'title' => __('New order'),
            'body' => __(':customer placed order :number at :place (total for your place: :subtotal).', [
                'customer' => $this->customerName,
                'number' => $this->orderNumber,
                'place' => $this->placeName,
                'subtotal' => $this->placeSubtotal,
            ]),
            'action' => [
                'type' => 'place_order',
                'place_id' => $this->placeId,
                'order_id' => $this->orderId,
            ],
        ];
    }
}
