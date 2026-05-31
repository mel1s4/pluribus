<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestInvitationVerifyEmailRequest;
use App\Http\Requests\RegisterViaInvitationVerifiedRequest;
use App\Http\Resources\UserResource;
use App\Mail\JoinInvitationEmailVerificationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityInvitationEmailVerification;
use App\Models\CommunityMembership;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Support\LocaleOptions;
use App\Support\WalletLedger\LedgerAppender;
use App\Support\WalletMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JoinInvitationController extends Controller
{
    public function __construct(private LedgerAppender $ledgerAppender) {}

    public function show(Request $request, string $token): JsonResponse
    {
        if (! $this->tokenLooksValid($token)) {
            return response()->json([
                'valid' => false,
                'reason' => 'invalid_token',
            ]);
        }

        $invitation = CommunityInvitation::findByPlainToken($token);
        if ($invitation === null) {
            return response()->json([
                'valid' => false,
                'reason' => 'invalid_token',
            ]);
        }

        $invitation->loadMissing('community');

        $reason = $invitation->failureReason();
        if ($reason !== null) {
            return response()->json(array_merge([
                'valid' => false,
                'reason' => $reason,
                'community_name' => $invitation->community?->name,
                'default_language' => $this->inviteUiLanguage($invitation->community),
            ], $this->invitationCommunityMeta($invitation->community)));
        }

        return response()->json(array_merge([
            'valid' => true,
            'community_name' => $invitation->community?->name,
            'default_language' => $this->inviteUiLanguage($invitation->community),
            'max_uses' => $invitation->max_uses,
            'uses_count' => $invitation->uses_count,
            'uses_remaining' => $invitation->usesRemaining(),
            'locked_email' => $invitation->email !== null && $invitation->email !== '',
            'email' => $invitation->email,
        ], $this->invitationCommunityMeta($invitation->community)));
    }

    public function requestVerifyEmail(RequestInvitationVerifyEmailRequest $request, string $token): JsonResponse
    {
        if (! $this->tokenLooksValid($token)) {
            throw ValidationException::withMessages([
                'token' => [__('This invitation link is not valid.')],
            ]);
        }

        $invitation = CommunityInvitation::findByPlainToken($token);
        if ($invitation === null || ! $invitation->isUsable()) {
            throw ValidationException::withMessages([
                'token' => [__('This invitation link is not valid.')],
            ]);
        }

        $validated = $request->validated();
        $email = strtolower(trim($validated['email']));

        $inviteEmail = $invitation->email !== null && $invitation->email !== ''
            ? strtolower(trim($invitation->email))
            : null;
        if ($inviteEmail !== null && $inviteEmail !== $email) {
            throw ValidationException::withMessages([
                'email' => [__('This invitation was sent to a different email address.')],
            ]);
        }

        if (User::query()->where('email', $email)->exists()) {
            return response()->json(['ok' => true]);
        }

        $invitation->loadMissing('community');
        $community = $invitation->community;

        CommunityInvitationEmailVerification::query()
            ->where('community_invitation_id', $invitation->id)
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->delete();

        $plainVerify = Str::random(48);
        CommunityInvitationEmailVerification::query()->create([
            'community_invitation_id' => $invitation->id,
            'email' => $email,
            'token_hash' => CommunityInvitationEmailVerification::hashPlainToken($plainVerify),
            'expires_at' => now()->addHours(24),
            'consumed_at' => null,
        ]);

        $joinPath = $this->joinPathForCommunity($community);
        $base = rtrim((string) config('app.frontend_url'), '/');
        $completionUrl = $base.'/'.$joinPath.'/'.$token.'/verify/'.$plainVerify;

        try {
            Mail::to($email)->send(new JoinInvitationEmailVerificationMail(
                $completionUrl,
                (string) ($community?->name ?? ''),
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true]);
    }

    public function showVerify(Request $request, string $token, string $verifyToken): JsonResponse
    {
        if (! $this->tokenLooksValid($token) || ! $this->tokenLooksValid($verifyToken)) {
            return response()->json([
                'valid' => false,
                'reason' => 'invalid_token',
            ]);
        }

        $invitation = CommunityInvitation::findByPlainToken($token);
        if ($invitation === null) {
            return response()->json([
                'valid' => false,
                'reason' => 'invalid_token',
            ]);
        }

        $invitation->loadMissing('community');

        $reason = $invitation->failureReason();
        if ($reason !== null) {
            return response()->json(array_merge([
                'valid' => false,
                'reason' => $reason,
                'community_name' => $invitation->community?->name,
                'default_language' => $this->inviteUiLanguage($invitation->community),
            ], $this->invitationCommunityMeta($invitation->community)));
        }

        $verification = CommunityInvitationEmailVerification::findByPlainTokenForInvitation($invitation->id, $verifyToken);
        if ($verification === null || ! $verification->isUsable()) {
            return response()->json(array_merge([
                'valid' => false,
                'reason' => 'invalid_verification',
                'community_name' => $invitation->community?->name,
                'default_language' => $this->inviteUiLanguage($invitation->community),
            ], $this->invitationCommunityMeta($invitation->community)));
        }

        return response()->json(array_merge([
            'valid' => true,
            'email_verified' => true,
            'email' => $verification->email,
            'community_name' => $invitation->community?->name,
            'default_language' => $this->inviteUiLanguage($invitation->community),
            'max_uses' => $invitation->max_uses,
            'uses_count' => $invitation->uses_count,
            'uses_remaining' => $invitation->usesRemaining(),
            'locked_email' => true,
        ], $this->invitationCommunityMeta($invitation->community)));
    }

    public function registerVerified(RegisterViaInvitationVerifiedRequest $request, string $token, string $verifyToken): JsonResponse
    {
        if (! $this->tokenLooksValid($token) || ! $this->tokenLooksValid($verifyToken)) {
            throw ValidationException::withMessages([
                'token' => [__('This invitation link is not valid.')],
            ]);
        }

        $validated = $request->validated();

        $user = DB::transaction(function () use ($token, $verifyToken, $validated) {
            $invitation = CommunityInvitation::query()
                ->where('token_hash', CommunityInvitation::hashPlainToken($token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw ValidationException::withMessages([
                    'token' => [__('This invitation link is not valid.')],
                ]);
            }

            if (! $invitation->isUsable()) {
                throw ValidationException::withMessages([
                    'token' => [__('This invitation can no longer be used.')],
                ]);
            }

            $verification = CommunityInvitationEmailVerification::query()
                ->where('community_invitation_id', $invitation->id)
                ->where('token_hash', CommunityInvitationEmailVerification::hashPlainToken($verifyToken))
                ->lockForUpdate()
                ->first();

            if ($verification === null || ! $verification->isUsable()) {
                throw ValidationException::withMessages([
                    'token' => [__('This verification link is not valid or has expired.')],
                ]);
            }

            $email = strtolower(trim($verification->email));

            $created = User::query()->where('email', $email)->first();
            if (! $created instanceof User) {
                $created = User::query()->create([
                    'name' => $validated['name'],
                    'email' => $email,
                    'username' => null,
                    'password' => $validated['password'],
                    'user_type' => 'member',
                    'is_root' => false,
                ]);
            }

            $membership = CommunityMembership::query()
                ->where('community_id', $invitation->community_id)
                ->where('user_id', $created->id)
                ->lockForUpdate()
                ->first();
            $createdMembership = false;
            if (! $membership instanceof CommunityMembership) {
                $membership = CommunityMembership::query()->create([
                    'community_id' => $invitation->community_id,
                    'user_id' => $created->id,
                    'role' => 'member',
                ]);
                $createdMembership = true;
            } elseif ($membership->role !== 'member') {
                $membership->forceFill(['role' => 'member'])->save();
            }

            $verification->forceFill(['consumed_at' => now()])->save();
            $invitation->increment('uses_count');
            $invitation->refresh();

            if ($createdMembership && $invitation->canMintGrant()) {
                $this->mintInvitationGrant($invitation, $created);
            }

            return $created;
        });

        Auth::login($user, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($user->fresh()),
        ], 201);
    }

    private function joinPathForCommunity(?Community $community): string
    {
        if ($community === null) {
            return 'join';
        }
        $code = (string) $community->default_language;

        return in_array($code, LocaleOptions::codes(), true) && $code === 'es' ? 'invitacion' : 'join';
    }

    private function tokenLooksValid(string $token): bool
    {
        $len = strlen($token);

        return $len >= 16 && $len <= 200 && preg_match('/^[A-Za-z0-9]+$/', $token) === 1;
    }

    private function inviteUiLanguage(?Community $community): string
    {
        if ($community === null) {
            return LocaleOptions::default();
        }
        $code = (string) $community->default_language;

        return in_array($code, LocaleOptions::codes(), true) ? $code : LocaleOptions::default();
    }

    /**
     * @return array{community_slug: string|null}
     */
    private function invitationCommunityMeta(?Community $community): array
    {
        if ($community === null) {
            return [
                'community_slug' => null,
                'community_logo_url' => null,
            ];
        }
        $slug = trim((string) ($community->slug ?? ''));

        return [
            'community_slug' => $slug !== '' ? $slug : null,
            'community_logo_url' => $community->publicLogoUrl(),
        ];
    }

    private function mintInvitationGrant(CommunityInvitation $invitation, User $recipient): void
    {
        $amount = $invitation->grant_credits;
        if (! is_string($amount) || ! WalletMoney::isPositive($amount)) {
            return;
        }

        $wallet = Wallet::query()
            ->where('community_id', $invitation->community_id)
            ->where('user_id', $recipient->id)
            ->lockForUpdate()
            ->first();
        if (! $wallet instanceof Wallet) {
            $wallet = Wallet::firstOrCreateForMember((int) $invitation->community_id, (int) $recipient->id);
            $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
        }

        $wallet->balance = WalletMoney::add((string) $wallet->balance, $amount);
        $wallet->save();

        $community = Community::query()->whereKey($invitation->community_id)->first();
        $lang = $this->inviteUiLanguage($community);
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
}
