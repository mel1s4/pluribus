<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterViaInvitationRequest;
use App\Http\Resources\UserResource;
use App\Models\CommunityInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinInvitationController extends Controller
{
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
            return response()->json([
                'valid' => false,
                'reason' => $reason,
                'community_name' => $invitation->community?->name,
            ]);
        }

        return response()->json([
            'valid' => true,
            'community_name' => $invitation->community?->name,
            'max_uses' => $invitation->max_uses,
            'uses_count' => $invitation->uses_count,
            'uses_remaining' => $invitation->usesRemaining(),
            'locked_email' => $invitation->email !== null && $invitation->email !== '',
            'email' => $invitation->email,
        ]);
    }

    public function register(RegisterViaInvitationRequest $request, string $token): JsonResponse
    {
        if (! $this->tokenLooksValid($token)) {
            throw ValidationException::withMessages([
                'token' => [__('This invitation link is not valid.')],
            ]);
        }

        $validated = $request->validated();
        $email = strtolower(trim($validated['email']));

        $user = DB::transaction(function () use ($token, $validated, $email) {
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

            $inviteEmail = $invitation->email !== null && $invitation->email !== ''
                ? strtolower(trim($invitation->email))
                : null;
            if ($inviteEmail !== null && $inviteEmail !== $email) {
                throw ValidationException::withMessages([
                    'email' => [__('This invitation was sent to a different email address.')],
                ]);
            }

            $created = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'username' => null,
                'password' => $validated['password'],
                'user_type' => 'member',
                'is_root' => false,
            ]);

            $invitation->increment('uses_count');

            return $created;
        });

        Auth::login($user, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($user->fresh()),
        ], 201);
    }

    private function tokenLooksValid(string $token): bool
    {
        $len = strlen($token);

        return $len >= 16 && $len <= 200 && preg_match('/^[A-Za-z0-9]+$/', $token) === 1;
    }
}
