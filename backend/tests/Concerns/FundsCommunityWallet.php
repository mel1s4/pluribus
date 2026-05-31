<?php

namespace Tests\Concerns;

use App\Models\Community;
use App\Models\User;
use App\Models\Wallet;
use App\Support\WalletMoney;

trait FundsCommunityWallet
{
    protected function ensureCommunityMember(User $user, ?Community $community = null): Community
    {
        $community ??= Community::current();
        $user->communities()->syncWithoutDetaching([$community->id => ['role' => 'member']]);

        return $community;
    }

    protected function fundMemberWallet(User $user, string $amount, ?Community $community = null): Wallet
    {
        $community = $this->ensureCommunityMember($user, $community);
        $wallet = Wallet::firstOrCreateForMember((int) $community->id, (int) $user->id);
        $wallet->balance = WalletMoney::normalize($amount);
        $wallet->save();

        return $wallet;
    }
}
