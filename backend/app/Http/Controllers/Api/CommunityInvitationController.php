<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use App\Support\CommunityPivotAdmin;
use App\Support\LocaleOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CommunityInvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('invitations.manage');

        $community = $this->activeCommunity($request);
        CommunityPivotAdmin::assertRootOrPivotAdmin($request->user(), $community);
        $rows = CommunityInvitation::query()
            ->where('community_id', $community->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (CommunityInvitation $invitation) => $this->invitationSummary($invitation)),
        ]);
    }

    public function destroy(Request $request, CommunityInvitation $invitation): JsonResponse
    {
        $this->authorize('invitations.manage');

        $community = $this->activeCommunity($request);
        CommunityPivotAdmin::assertRootOrPivotAdmin($request->user(), $community);

        if ((int) $invitation->community_id !== (int) $community->id) {
            abort(404);
        }

        $invitation->delete();

        return response()->json(null, 204);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        if (! $user->can('users.create') && ! $user->can('invitations.manage')) {
            $this->authorize('users.create');
        }

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'grant_credits' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'grant_limit_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // When set (e.g. from the signed-in admin's UI language), shapes the SPA path after the
            // share page redirect (/join/… vs /invitacion/…). Falls back to the community default when omitted.
            'join_url_locale' => ['nullable', 'string', 'in:en,es'],
        ]);

        $email = isset($validated['email']) && $validated['email'] !== ''
            ? $validated['email']
            : null;

        // Email invitations always use a single-use link; usage limits apply only to
        // anonymous "create link" invitations (no email).
        if ($email !== null) {
            $maxUses = 1;
        } else {
            $maxUses = array_key_exists('max_uses', $validated) && $validated['max_uses'] !== null
                ? (int) $validated['max_uses']
                : null;
        }
        $grantCredits = array_key_exists('grant_credits', $validated) && $validated['grant_credits'] !== null
            ? \App\Support\WalletMoney::normalize((string) $validated['grant_credits'])
            : null;
        $grantLimitUses = array_key_exists('grant_limit_uses', $validated) && $validated['grant_limit_uses'] !== null
            ? (int) $validated['grant_limit_uses']
            : null;

        if ($grantLimitUses !== null && $maxUses !== null) {
            abort(422, __('The grant_limit_uses field is only allowed for unlimited invitations.'));
        }
        if ($grantLimitUses !== null && $grantCredits === null) {
            abort(422, __('The grant_limit_uses field requires grant_credits.'));
        }

        $community = $this->activeCommunity($request);
        CommunityPivotAdmin::assertRootOrPivotAdmin($user, $community);
        $plainToken = Str::random(48);
        $tokenHash = CommunityInvitation::hashPlainToken($plainToken);

        $invitation = CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $request->user()->id,
            'token_hash' => $tokenHash,
            'email' => $email,
            'max_uses' => $maxUses,
            'uses_count' => 0,
            'grant_credits' => $grantCredits,
            'grant_limit_uses' => $grantLimitUses,
            'grant_uses_count' => 0,
            'expires_at' => now()->addDays(14),
            'revoked_at' => null,
        ]);

        $redirectLocale = 'en';
        if (
            isset($validated['join_url_locale'])
            && is_string($validated['join_url_locale'])
            && in_array($validated['join_url_locale'], LocaleOptions::codes(), true)
        ) {
            $redirectLocale = $validated['join_url_locale'];
        } else {
            $ccLang = (string) $community->default_language;
            if (in_array($ccLang, LocaleOptions::codes(), true)) {
                $redirectLocale = $ccLang;
            }
        }
        $shareBase = rtrim((string) config('app.join_share_base_url'), '/');
        $joinUrl = $shareBase.'/join-invitation-share/'.$plainToken
            .'?redirect_locale='.rawurlencode($redirectLocale);

        $emailSent = false;
        if ($email !== null) {
            try {
                Mail::to($email)->send(new CommunityInvitationMail($joinUrl, $community->name));
                $emailSent = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'invitation' => [
                'id' => $invitation->id,
                'join_url' => $joinUrl,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'email' => $invitation->email,
                'email_sent' => $emailSent,
                'max_uses' => $invitation->max_uses,
                'grant_credits' => $invitation->grant_credits,
                'grant_limit_uses' => $invitation->grant_limit_uses,
                'grant_uses_count' => (int) $invitation->grant_uses_count,
                'grant_max_mint_total' => $this->maxGrantMintTotal($invitation),
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationSummary(CommunityInvitation $invitation): array
    {
        $kind = $invitation->email !== null && $invitation->email !== '' ? 'email' : 'link';

        return [
            'id' => $invitation->id,
            'kind' => $kind,
            'email' => $invitation->email,
            'max_uses' => $invitation->max_uses,
            'uses_count' => (int) $invitation->uses_count,
            'grant_credits' => $invitation->grant_credits,
            'grant_limit_uses' => $invitation->grant_limit_uses,
            'grant_uses_count' => (int) $invitation->grant_uses_count,
            'grant_uses_remaining' => $invitation->remainingGrantUses(),
            'grant_max_mint_total' => $this->maxGrantMintTotal($invitation),
            'has_been_used' => (int) $invitation->uses_count > 0,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'revoked_at' => $invitation->revoked_at?->toIso8601String(),
            'created_at' => $invitation->created_at?->toIso8601String(),
            'is_usable' => $invitation->isUsable(),
            'failure_reason' => $invitation->failureReason(),
        ];
    }

    private function activeCommunity(Request $request): Community
    {
        $active = $request->attributes->get('active_community');
        if ($active instanceof Community) {
            return $active;
        }

        return Community::current();
    }

    private function maxGrantMintTotal(CommunityInvitation $invitation): ?string
    {
        if ($invitation->grant_credits === null) {
            return null;
        }

        $capUses = $invitation->max_uses !== null
            ? (int) $invitation->max_uses
            : ($invitation->grant_limit_uses !== null ? (int) $invitation->grant_limit_uses : null);
        if ($capUses === null) {
            return null;
        }

        return \App\Support\WalletMoney::mul((string) $invitation->grant_credits, (string) $capUses);
    }
}
