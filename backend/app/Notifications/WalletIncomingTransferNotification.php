<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WalletIncomingTransferNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $communityId,
        public string $communitySlug,
        public int $walletTransactionId,
        public string $amount,
        public string $senderName,
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
            'kind' => 'wallet_transfer_in',
            'title' => __('Wallet credit received'),
            'body' => __('You received :amount from :sender.', [
                'amount' => $this->amount,
                'sender' => $this->senderName,
            ]),
            'action' => [
                'type' => 'wallet_movement',
                'community_id' => $this->communityId,
                'community_slug' => $this->communitySlug,
                'wallet_transaction_id' => $this->walletTransactionId,
            ],
        ];
    }
}
