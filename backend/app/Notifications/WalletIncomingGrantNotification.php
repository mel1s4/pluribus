<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WalletIncomingGrantNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $communityId,
        public string $communitySlug,
        public int $walletTransactionId,
        public string $amount,
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
            'kind' => 'wallet_grant_in',
            'title' => __('Community grant received'),
            'body' => __('Your wallet was credited :amount.', ['amount' => $this->amount]),
            'action' => [
                'type' => 'wallet_movement',
                'community_id' => $this->communityId,
                'community_slug' => $this->communitySlug,
                'wallet_transaction_id' => $this->walletTransactionId,
            ],
        ];
    }
}
