<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChangedForBuyerNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $placeName,
        public string $previousStatus,
        public string $newStatus,
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
            'kind' => 'order_status_changed',
            'title' => __('Order status updated'),
            'body' => __('Order :number at :place is now :status.', [
                'number' => $this->orderNumber,
                'place' => $this->placeName,
                'status' => $this->newStatus,
            ]),
            'action' => [
                'type' => 'buyer_order',
                'order_id' => $this->orderId,
            ],
            'meta' => [
                'previous_status' => $this->previousStatus,
                'new_status' => $this->newStatus,
            ],
        ];
    }
}
