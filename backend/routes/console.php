<?php

use App\Support\WalletLedger\LedgerAppender;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('wallet:ledger-gen-keys', function (): int {
    if (! function_exists('sodium_crypto_sign_keypair')) {
        $this->error('The sodium extension is required.');

        return 1;
    }

    $kp = sodium_crypto_sign_keypair();
    $secret = base64_encode(sodium_crypto_sign_secretkey($kp));
    $public = base64_encode(sodium_crypto_sign_publickey($kp));
    $this->line('Add to .env (keep WALLET_LEDGER_SECRET_KEY private):');
    $this->line('WALLET_LEDGER_SECRET_KEY='.$secret);
    $this->line('WALLET_LEDGER_PUBLIC_KEY='.$public);

    return 0;
})->purpose('Print a new Ed25519 keypair for the signed wallet ledger');

Artisan::command('wallet:ledger-seal-open {community_id}', function (string $communityId): int {
    $id = (int) $communityId;
    if ($id <= 0) {
        $this->error('Invalid community_id.');

        return 1;
    }
    $app = app(LedgerAppender::class);
    $ok = $app->sealOpenBlockIfNeeded($id);
    if (! $ok) {
        $this->warn('No open block with entries to seal.');

        return 0;
    }
    $this->info('Sealed open block for community '.$id.'.');

    return 0;
})->purpose('Seal the current partial wallet ledger block (when entries_per_block > 1)');
