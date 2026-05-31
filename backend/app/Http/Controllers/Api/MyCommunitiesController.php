<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\CommunityInvitation;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Support\LocaleOptions;
use App\Support\WalletLedger\LedgerAppender;
use App\Support\WalletMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MyCommunitiesController extends Controller
{
    public function __construct(private LedgerAppender $ledgerAppender) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $rows = $user->communities()
            ->orderBy('communities.name')
            ->get(['communities.id', 'communities.name', 'communities.slug'])
            ->map(fn (Community $community): array => [
                'id' => $community->id,
                'name' => $community->name,
                'slug' => $community->slug,
                'role' => (string) ($community->pivot?->role ?? 'member'),
            ])
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function joinByInvitation(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        DB::transaction(function () use ($token, $user): void {
            $invitation = CommunityInvitation::query()
                ->where('token_hash', CommunityInvitation::hashPlainToken($token))
                ->lockForUpdate()
                ->first();
            if (! $invitation instanceof CommunityInvitation || ! $invitation->isUsable()) {
                abort(422, 'Invitation is not valid.');
            }

            $membership = CommunityMembership::query()
                ->where('community_id', $invitation->community_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            $createdMembership = false;
            if (! $membership instanceof CommunityMembership) {
                CommunityMembership::query()->create([
                    'community_id' => $invitation->community_id,
                    'user_id' => $user->id,
                    'role' => 'member',
                ]);
                $createdMembership = true;
            } elseif ($membership->role !== 'member') {
                $membership->forceFill(['role' => 'member'])->save();
            }

            $invitation->increment('uses_count');
            $invitation->refresh();

            if ($createdMembership && $invitation->canMintGrant()) {
                $this->mintInvitationGrant($invitation, $user->id);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function mintInvitationGrant(CommunityInvitation $invitation, int $userId): void
    {
        $amount = $invitation->grant_credits;
        if (! is_string($amount) || ! WalletMoney::isPositive($amount)) {
            return;
        }

        $wallet = Wallet::query()
            ->where('community_id', $invitation->community_id)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
        if (! $wallet instanceof Wallet) {
            $wallet = Wallet::firstOrCreateForMember((int) $invitation->community_id, $userId);
            $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
        }

        $wallet->balance = WalletMoney::add((string) $wallet->balance, $amount);
        $wallet->save();

        $lang = $this->inviteUiLanguage((int) $invitation->community_id);
        $note = trans('invitations.auto_grant_note', [], $lang);

        $this->ledgerAppender->append(
            (int) $invitation->community_id,
            WalletLedgerEntry::TYPE_GRANT,
            $amount,
            null,
            (string) $wallet->public_ref,
            WalletLedgerEntry::ACTOR_COMMUNITY_GRANT,
            $note
        );

        $invitation->increment('grant_uses_count');
    }

    private function inviteUiLanguage(int $communityId): string
    {
        $code = (string) Community::query()->whereKey($communityId)->value('default_language');

        return in_array($code, LocaleOptions::codes(), true) ? $code : LocaleOptions::default();
    }
}
