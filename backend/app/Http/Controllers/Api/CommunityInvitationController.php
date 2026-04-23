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
    public function store(Request $request): JsonResponse
    {
        $this->authorize('users.create');

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
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

        $joinPath = $community->default_language === 'es' ? 'invitacion' : 'join';
        if (! in_array((string) $community->default_language, LocaleOptions::codes(), true)) {
            $joinPath = 'join';
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
}
