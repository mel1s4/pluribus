<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
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

        $community = Community::current();
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

        if ((int) $invitation->community_id !== (int) Community::current()->id) {
            abort(404);
        }

        $invitation->delete();

        return response()->json(null, 204);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->can('users.create') && ! $user->can('invitations.manage')) {
            $this->authorize('users.create');
        }

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // When set (e.g. from the signed-in admin's UI language), shapes the public join path
            // (/join/… vs /invitacion/…). Falls back to the community default when omitted.
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

        $community = Community::current();
        $plainToken = Str::random(48);
        $tokenHash = CommunityInvitation::hashPlainToken($plainToken);

        $invitation = CommunityInvitation::query()->create([
            'community_id' => $community->id,
            'created_by' => $request->user()->id,
            'token_hash' => $tokenHash,
            'email' => $email,
            'max_uses' => $maxUses,
            'uses_count' => 0,
            'expires_at' => now()->addDays(14),
            'revoked_at' => null,
        ]);

        $joinPath = 'join';
        if (
            isset($validated['join_url_locale'])
            && is_string($validated['join_url_locale'])
            && in_array($validated['join_url_locale'], LocaleOptions::codes(), true)
        ) {
            $joinPath = $validated['join_url_locale'] === 'es' ? 'invitacion' : 'join';
        } else {
            $ccLang = (string) $community->default_language;
            if (in_array($ccLang, LocaleOptions::codes(), true)) {
                $joinPath = $ccLang === 'es' ? 'invitacion' : 'join';
            }
        }
        $joinUrl = rtrim((string) config('app.frontend_url'), '/').'/'.$joinPath.'/'.$plainToken;

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
            'has_been_used' => (int) $invitation->uses_count > 0,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'revoked_at' => $invitation->revoked_at?->toIso8601String(),
            'created_at' => $invitation->created_at?->toIso8601String(),
            'is_usable' => $invitation->isUsable(),
            'failure_reason' => $invitation->failureReason(),
        ];
    }
}
