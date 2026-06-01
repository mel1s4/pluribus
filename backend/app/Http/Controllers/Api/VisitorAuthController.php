<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestVisitorLoginLinkRequest;
use App\Http\Resources\UserResource;
use App\Mail\VisitorLoginMail;
use App\Models\Community;
use App\Models\User;
use App\Models\VisitorLoginToken;
use App\Support\CommunityGuestAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VisitorAuthController extends Controller
{
    public function requestLoginLink(RequestVisitorLoginLinkRequest $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->validated('email')));
        $community = $this->activeCommunity($request);

        $user = $this->findUserByEmail($email);
        if (! $user instanceof User) {
            $user = User::query()->create([
                'name' => Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(40),
                'user_type' => 'visitor',
                'is_root' => false,
            ]);
        }

        $plainToken = Str::random(48);
        VisitorLoginToken::query()->create([
            'user_id' => $user->id,
            'community_id' => $community?->id,
            'email' => $email,
            'token_hash' => VisitorLoginToken::hashPlainToken($plainToken),
            'expires_at' => now()->addMinutes(30),
            'consumed_at' => null,
        ]);

        $loginUrl = $this->visitorLoginUrl($plainToken, $community);

        try {
            Mail::to($email)->queue(new VisitorLoginMail($loginUrl));
        } catch (\Throwable $e) {
            report($e);
            Log::warning('auth.visitor_link.mail_queue_failed', [
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function consumeLoginLink(Request $request, string $token): JsonResponse
    {
        $len = strlen($token);
        if ($len < 16 || $len > 200 || preg_match('/^[A-Za-z0-9]+$/', $token) !== 1) {
            throw ValidationException::withMessages(['token' => [__('Invalid login link.')]]);
        }

        $community = $this->activeCommunity($request);

        $user = DB::transaction(function () use ($token, $community): User {
            $row = VisitorLoginToken::query()
                ->where('token_hash', VisitorLoginToken::hashPlainToken($token))
                ->lockForUpdate()
                ->first();
            if (! $row instanceof VisitorLoginToken || ! $row->isUsable()) {
                throw ValidationException::withMessages(['token' => [__('This login link is no longer valid.')]]);
            }
            $row->forceFill(['consumed_at' => now()])->save();
            $user = $row->user()->first();
            if (! $user instanceof User) {
                throw ValidationException::withMessages(['token' => [__('Invalid login link.')]]);
            }

            $targetCommunity = $community;
            if ($targetCommunity === null && $row->community_id !== null) {
                $targetCommunity = Community::query()->find((int) $row->community_id);
            }

            if ($targetCommunity instanceof Community) {
                CommunityGuestAccess::ensureVisitorMembership($user, $targetCommunity);
            }

            return $user;
        });

        Auth::login($user, false);
        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($user->fresh()),
        ]);
    }

    private function activeCommunity(Request $request): ?Community
    {
        $active = $request->attributes->get('active_community');

        return $active instanceof Community ? $active : null;
    }

    private function visitorLoginUrl(string $plainToken, ?Community $community): string
    {
        $base = $community?->publicSiteUrl();
        if ($base === null || $base === '') {
            $base = rtrim((string) config('app.frontend_url'), '/');
        }

        return $base.'/visitor-auth/'.$plainToken;
    }

    private function findUserByEmail(string $email): ?User
    {
        $exact = User::query()->where('email', $email)->first();
        if ($exact instanceof User) {
            return $exact;
        }

        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }
}
