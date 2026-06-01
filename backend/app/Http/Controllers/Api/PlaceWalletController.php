<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\WalletLedgerOwnerFormatting;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceWalletTransferRequest;
use App\Models\Community;
use App\Models\Place;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Models\WalletPrivilegedAudit;
use App\Support\WalletLedger\LedgerAppender;
use App\Support\WalletMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceWalletController extends Controller
{
    use WalletLedgerOwnerFormatting;

    public function __construct(
        private LedgerAppender $ledgerAppender,
    ) {}

    public function show(Request $request, Place $place): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('viewPlaceWallet', $place);

        $community = Community::forRequest($request);
        $communityId = (int) $community->id;
        $this->assertMemberOfCommunity($user, $communityId);

        $wallet = Wallet::firstOrCreateForPlace($communityId, (int) $place->id);
        $wallet->refresh();

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $ref = $wallet->public_ref;

        $paginator = WalletLedgerEntry::query()
            ->where('community_id', $communityId)
            ->where(function ($q) use ($ref): void {
                $q->where('from_public_ref', $ref)
                    ->orWhere('to_public_ref', $ref);
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $byRef = $this->loadWalletsByPublicRefs(
            $communityId,
            $this->collectPublicRefsFromPage($paginator->getCollection())
        );

        $data = $paginator->getCollection()->map(function ($tx) use ($wallet, $byRef): array {
            return $this->formatOwnerTransaction($tx, $wallet, $byRef);
        })->values();

        return response()->json([
            'currency' => $this->currencyDisplayForCommunity($community),
            'wallet' => [
                'public_ref' => $wallet->public_ref,
                'balance' => (string) $wallet->balance,
            ],
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function transfer(StorePlaceWalletTransferRequest $request, Place $place): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            abort(401);
        }
        $this->authorize('transferFromPlaceWallet', $place);

        $community = Community::forRequest($request);
        $communityId = (int) $community->id;
        $this->assertMemberOfCommunity($actor, $communityId);

        $recipientEmail = (string) $request->validated('recipient_email');
        $amount = WalletMoney::normalize($request->validated('amount'));
        if (! WalletMoney::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => [__('Invalid amount.')]]);
        }

        $recipient = User::query()->where('email', $recipientEmail)->first();
        if ($recipient === null) {
            throw ValidationException::withMessages(['recipient_email' => [__('No user with that email.')]]);
        }
        $this->assertMemberOfCommunity($recipient, $communityId);

        DB::transaction(function () use ($actor, $recipient, $communityId, $amount, $place, $request): void {
            $fromStub = Wallet::firstOrCreateForPlace($communityId, (int) $place->id);
            $toStub = Wallet::firstOrCreateForMember($communityId, (int) $recipient->id);

            $ids = [(int) $fromStub->id, (int) $toStub->id];
            sort($ids);
            Wallet::query()->whereKey($ids[0])->lockForUpdate()->firstOrFail();
            Wallet::query()->whereKey($ids[1])->lockForUpdate()->firstOrFail();

            $from = Wallet::query()->whereKey($fromStub->id)->firstOrFail();
            $to = Wallet::query()->whereKey($toStub->id)->firstOrFail();

            if (WalletMoney::compare((string) $from->balance, $amount) < 0) {
                throw ValidationException::withMessages(['amount' => [__('Insufficient balance.')]]);
            }

            $from->balance = WalletMoney::sub((string) $from->balance, $amount);
            $from->save();

            $to->balance = WalletMoney::add((string) $to->balance, $amount);
            $to->save();

            [$entry] = $this->ledgerAppender->append(
                $communityId,
                WalletLedgerEntry::TYPE_TRANSFER,
                $amount,
                $from->public_ref,
                $to->public_ref,
                WalletLedgerEntry::ACTOR_PLACE_TREASURY_TRANSFER,
                $request->validated('note'),
            );

            WalletPrivilegedAudit::query()->create([
                'wallet_ledger_entry_id' => $entry->id,
                'actor_user_id' => $actor->id,
            ]);
        });

        return response()->json(['ok' => true]);
    }

    private function assertMemberOfCommunity(User $user, int $communityId): void
    {
        $exists = $user->communities()->where('communities.id', $communityId)->exists();
        if (! $exists) {
            abort(403, __('You are not a member of this community.'));
        }
    }
}
