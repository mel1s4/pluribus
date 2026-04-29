<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestVisitorLoginLinkRequest;
use App\Http\Resources\UserResource;
use App\Mail\VisitorLoginMail;
use App\Models\User;
use App\Models\VisitorLoginToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VisitorAuthController extends Controller
{
    public function requestLoginLink(RequestVisitorLoginLinkRequest $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->validated('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
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
            'email' => $email,
            'token_hash' => VisitorLoginToken::hashPlainToken($plainToken),
            'expires_at' => now()->addMinutes(30),
            'consumed_at' => null,
        ]);
        $loginUrl = rtrim((string) config('app.frontend_url'), '/').'/visitor-auth/'.$plainToken;

        try {
            Mail::to($email)->send(new VisitorLoginMail($loginUrl));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true]);
    }

    public function consumeLoginLink(Request $request, string $token): JsonResponse
    {
        $len = strlen($token);
        if ($len < 16 || $len > 200 || preg_match('/^[A-Za-z0-9]+$/', $token) !== 1) {
            throw ValidationException::withMessages(['token' => [__('Invalid login link.')]]);
        }

        $user = DB::transaction(function () use ($token): User {
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

            return $user;
        });

        Auth::login($user, false);
        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($user->fresh()),
        ]);
    }
}
