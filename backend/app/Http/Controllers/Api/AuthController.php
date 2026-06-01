<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Community;
use App\Models\User;
use App\Models\UserPersonificationAudit;
use App\Support\CommunityGuestAccess;
use App\Support\PersonificationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        $remember = $request->boolean('remember');

        $credentials = str_contains($login, '@')
            ? ['email' => $login, 'password' => $password]
            : ['username' => $login, 'password' => $password];

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $community = $request->attributes->get('active_community');
        if ($community instanceof Community) {
            $intent = (string) $request->input('intent', 'member');
            $hasMembership = CommunityGuestAccess::hasMembership($user, $community);
            if ($intent === 'guest') {
                CommunityGuestAccess::ensureVisitorMembership($user, $community);
            } elseif (! $hasMembership) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => [__('You are not a member of this community. Use guest access or join with an invitation.')],
                ]);
            }
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($user->fresh()),
            'personification' => ['active' => false],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $payload = PersonificationSession::payload($request);
        if (is_array($payload)) {
            $actorId = (int) ($payload['actor_user_id'] ?? 0);
            $targetId = (int) ($payload['target_user_id'] ?? 0);
            if ($actorId > 0 && $targetId > 0) {
                UserPersonificationAudit::query()->create([
                    'actor_user_id' => $actorId,
                    'target_user_id' => $targetId,
                    'action' => UserPersonificationAudit::ACTION_FORCED_LOGOUT,
                    'reason' => 'Session ended by logout',
                    'ticket_reference' => isset($payload['ticket_reference']) ? (string) $payload['ticket_reference'] : null,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                ]);
            }
        }
        PersonificationSession::forget($request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function user(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $body = [
            'user' => UserResource::make($user),
            'personification' => PersonificationSession::summaryForRequest($request),
        ];

        $response = response()->json($body);
        Log::debug('auth.user.response', [
            'user_id' => $user->id,
            'personification_active' => (bool) ($body['personification']['active'] ?? false),
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $response;
    }
}
