<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\WalletLedgerOwnerFormatting;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalletGrantRequest;
use App\Http\Requests\StoreWalletTransferRequest;
use App\Models\Community;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Models\WalletPrivilegedAudit;
use App\Notifications\WalletIncomingGrantNotification;
use App\Notifications\WalletIncomingTransferNotification;
use App\Support\WalletLedger\LedgerAppender;
use App\Support\WalletLedger\LedgerAuditRowFormatter;
use App\Support\WalletLedger\LedgerSigner;
use App\Support\WalletLedger\WalletLedgerTipPayload;
use App\Support\WalletMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    use WalletLedgerOwnerFormatting;

    public function __construct(
        private LedgerAppender $ledgerAppender,
        private LedgerSigner $ledgerSigner,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('wallet.view');

        $communityId = $this->requiredCommunityId($request);
        $this->assertMemberOfCommunity($user, $communityId);

        $wallet = Wallet::firstOrCreateForMember($communityId, $user->id);
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

        $data = $paginator->getCollection()->map(function (WalletLedgerEntry $tx) use ($wallet, $byRef): array {
            return $this->formatOwnerTransaction($tx, $wallet, $byRef);
        })->values();

        $community = Community::query()->whereKey($communityId)->first();
        $currency = $this->currencyDisplayForCommunity($community);

        return response()->json([
            'currency' => $currency,
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

    public function showTransaction(Request $request, WalletLedgerEntry $transaction): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('wallet.view');

        $communityId = $this->requiredCommunityId($request);
        $this->assertMemberOfCommunity($user, $communityId);

        if ((int) $transaction->community_id !== $communityId) {
            abort(404);
        }

        $wallet = Wallet::firstOrCreateForMember($communityId, $user->id);
        $wallet->refresh();
        $myRef = $wallet->public_ref;

        $involvesMyWallet = $transaction->from_public_ref === $myRef || $transaction->to_public_ref === $myRef;
        if (! $involvesMyWallet) {
            abort(404);
        }

        $byRef = $this->loadWalletsByPublicRefs(
            $communityId,
            $this->collectPublicRefsFromPage(collect([$transaction]))
        );

        $community = Community::query()->whereKey($communityId)->first();

        return response()->json([
            'currency' => $this->currencyDisplayForCommunity($community),
            'transaction' => $this->formatOwnerTransaction($transaction, $wallet, $byRef),
        ]);
    }

    public function transfer(StoreWalletTransferRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('wallet.transfer');

        $communityId = (int) $request->validated('community_id');
        $this->assertMemberOfCommunity($user, $communityId);

        $recipientEmail = (string) $request->validated('recipient_email');
        $amount = WalletMoney::normalize($request->validated('amount'));
        if (! WalletMoney::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => [__('Invalid amount.')]]);
        }

        $recipient = User::query()->where('email', $recipientEmail)->first();
        if ($recipient === null) {
            throw ValidationException::withMessages(['recipient_email' => [__('No user with that email.')]]);
        }
        if ((int) $recipient->id === (int) $user->id) {
            throw ValidationException::withMessages(['recipient_email' => [__('You cannot transfer to yourself.')]]);
        }
        $this->assertMemberOfCommunity($recipient, $communityId);

        $createdTxId = null;
        try {
            DB::transaction(function () use ($user, $recipient, $communityId, $amount, $request, &$createdTxId): void {
                $senderStub = Wallet::firstOrCreateForMember($communityId, $user->id);
                $receiverStub = Wallet::firstOrCreateForMember($communityId, $recipient->id);

                $ids = [(int) $senderStub->id, (int) $receiverStub->id];
                sort($ids);
                Wallet::query()->whereKey($ids[0])->lockForUpdate()->firstOrFail();
                Wallet::query()->whereKey($ids[1])->lockForUpdate()->firstOrFail();

                $sender = Wallet::query()->whereKey($senderStub->id)->firstOrFail();
                $receiver = Wallet::query()->whereKey($receiverStub->id)->firstOrFail();

                if (WalletMoney::compare((string) $sender->balance, $amount) < 0) {
                    throw ValidationException::withMessages(['amount' => [__('Insufficient balance.')]]);
                }

                $sender->balance = WalletMoney::sub((string) $sender->balance, $amount);
                $sender->save();

                $receiver->balance = WalletMoney::add((string) $receiver->balance, $amount);
                $receiver->save();

                [$entry] = $this->ledgerAppender->append(
                    $communityId,
                    WalletLedgerEntry::TYPE_TRANSFER,
                    $amount,
                    $sender->public_ref,
                    $receiver->public_ref,
                    WalletLedgerEntry::ACTOR_MEMBER_TRANSFER,
                    $request->validated('note'),
                );

                WalletPrivilegedAudit::query()->create([
                    'wallet_ledger_entry_id' => $entry->id,
                    'actor_user_id' => $user->id,
                ]);
                $createdTxId = (int) $entry->id;
            });
        } catch (\Throwable $e) {
            // Re-throw ValidationException to allow standard validation response
            if ($e instanceof ValidationException) {
                throw $e;
            }

            \Illuminate\Support\Facades\Log::error('Wallet transfer failed', [
                'community_id' => $communityId,
                'actor_id' => $user->id,
                'recipient_id' => $recipient->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An internal error occurred while processing the transfer.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }

        if ($createdTxId !== null) {
            $slug = $this->communitySlugForNotifications($communityId);
            $recipient->notify(new WalletIncomingTransferNotification(
                $communityId,
                $slug,
                $createdTxId,
                $amount,
                (string) $user->name,
            ));
        }

        return response()->json(['ok' => true]);
    }

    public function grant(StoreWalletGrantRequest $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            abort(401);
        }
        $this->authorize('wallet.grant');

        $communityId = (int) $request->validated('community_id');
        $this->assertMemberOfCommunity($actor, $communityId);

        $amount = WalletMoney::normalize($request->validated('amount'));
        if (! WalletMoney::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => [__('Invalid amount.')]]);
        }

        $recipient = $this->resolveGrantRecipient($request);
        $this->assertMemberOfCommunity($recipient, $communityId);

        $createdTxId = null;
        try {
            DB::transaction(function () use ($actor, $recipient, $communityId, $amount, $request, &$createdTxId): void {
                $toWallet = Wallet::query()
                    ->where('community_id', $communityId)
                    ->where('user_id', $recipient->id)
                    ->lockForUpdate()
                    ->first();
                if ($toWallet === null) {
                    $toWallet = Wallet::firstOrCreateForMember($communityId, $recipient->id);
                    $toWallet = Wallet::query()->whereKey($toWallet->id)->lockForUpdate()->firstOrFail();
                }

                $toWallet->balance = WalletMoney::add((string) $toWallet->balance, $amount);
                $toWallet->save();

                [$entry] = $this->ledgerAppender->append(
                    $communityId,
                    WalletLedgerEntry::TYPE_GRANT,
                    $amount,
                    null,
                    $toWallet->public_ref,
                    WalletLedgerEntry::ACTOR_COMMUNITY_GRANT,
                    $request->validated('note'),
                );

                WalletPrivilegedAudit::query()->create([
                    'wallet_ledger_entry_id' => $entry->id,
                    'actor_user_id' => $actor->id,
                ]);
                $createdTxId = (int) $entry->id;
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Wallet grant failed', [
                'community_id' => $communityId,
                'actor_id' => $actor->id,
                'recipient_id' => $recipient->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An internal error occurred while processing the grant.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }

        if ($createdTxId !== null) {
            $slug = $this->communitySlugForNotifications($communityId);
            $recipient->notify(new WalletIncomingGrantNotification(
                $communityId,
                $slug,
                $createdTxId,
                $amount,
            ));
        }

        return response()->json(['ok' => true]);
    }

    public function communityStats(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('wallet.grant');

        $communityId = $this->requiredCommunityId($request);
        $this->assertMemberOfCommunity($user, $communityId);

        $raw = WalletLedgerEntry::query()
            ->where('community_id', $communityId)
            ->where('type', WalletLedgerEntry::TYPE_GRANT)
            ->sum('amount');

        return response()->json([
            'credits_granted_total' => WalletMoney::normalize((string) ($raw ?? '0')),
        ]);
    }

    public function auditLedger(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('wallet.audit_ledger');

        $communityId = $this->requiredCommunityId($request);
        $this->assertMemberOfCommunity($user, $communityId);

        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);
        $paginator = WalletLedgerEntry::query()
            ->where('community_id', $communityId)
            ->with('ledgerBlock')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $paginator->getCollection()->map(fn (WalletLedgerEntry $tx): array => LedgerAuditRowFormatter::format($tx))->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function ledgerPublicKey(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('wallet.audit_ledger');

        $communityId = $this->requiredCommunityId($request);
        $this->assertMemberOfCommunity($user, $communityId);

        return response()->json([
            'algorithm' => 'ed25519',
            'public_key' => $this->ledgerSigner->publicKeyBase64(),
        ]);
    }

    public function ledgerTip(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('wallet.audit_ledger');

        $communityId = $this->requiredCommunityId($request);
        $this->assertMemberOfCommunity($user, $communityId);

        $tip = WalletLedgerTipPayload::latestSealedBlock($communityId);

        return response()->json([
            'tip' => WalletLedgerTipPayload::tipPayload($tip),
        ]);
    }

    public function auditIdentity(Request $request, WalletLedgerEntry $transaction): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $this->authorize('wallet.audit_identity');

        $communityId = $this->requiredCommunityId($request);
        if ((int) $transaction->community_id !== $communityId) {
            abort(404);
        }

        $privileged = WalletPrivilegedAudit::query()
            ->where('wallet_ledger_entry_id', $transaction->id)
            ->first();

        $fromUserId = null;
        $fromPlaceId = null;
        if (is_string($transaction->from_public_ref) && $transaction->from_public_ref !== '') {
            $from = Wallet::query()
                ->where('community_id', $communityId)
                ->where('public_ref', $transaction->from_public_ref)
                ->first(['user_id', 'place_id']);
            if ($from !== null) {
                $fromUserId = $from->user_id;
                $fromPlaceId = $from->place_id;
            }
        }

        $toUserId = null;
        $toPlaceId = null;
        $toWallet = Wallet::query()
            ->where('community_id', $communityId)
            ->where('public_ref', $transaction->to_public_ref)
            ->first(['user_id', 'place_id']);
        if ($toWallet !== null) {
            $toUserId = $toWallet->user_id;
            $toPlaceId = $toWallet->place_id;
        }

        return response()->json([
            'transaction_id' => $transaction->id,
            'actor_user_id' => $privileged?->actor_user_id,
            'from_user_id' => $fromUserId !== null ? (int) $fromUserId : null,
            'from_place_id' => $fromPlaceId !== null ? (int) $fromPlaceId : null,
            'to_user_id' => $toUserId !== null ? (int) $toUserId : null,
            'to_place_id' => $toPlaceId !== null ? (int) $toPlaceId : null,
        ]);
    }

    private function requiredCommunityId(Request $request): int
    {
        $id = (int) $request->input('community_id', $request->query('community_id', 0));
        if ($id <= 0) {
            throw ValidationException::withMessages([
                'community_id' => [__('community_id is required.')],
            ]);
        }

        return $id;
    }

    private function assertMemberOfCommunity(User $user, int $communityId): void
    {
        $exists = $user->communities()->where('communities.id', $communityId)->exists();
        if (! $exists) {
            abort(403, __('You are not a member of this community.'));
        }
    }

    private function resolveGrantRecipient(StoreWalletGrantRequest $request): User
    {
        if ($request->filled('user_id')) {
            return User::query()->findOrFail((int) $request->validated('user_id'));
        }

        $email = (string) $request->validated('email');

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function communitySlugForNotifications(int $communityId): string
    {
        $slug = Community::query()->whereKey($communityId)->value('slug');

        return is_string($slug) && $slug !== '' ? $slug : 'community';
    }

}
