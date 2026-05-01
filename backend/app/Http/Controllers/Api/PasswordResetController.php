<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestPasswordResetRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private const EXPIRES_IN_MINUTES = 60;

    public function request(RequestPasswordResetRequest $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->validated('email')));
        $ip = $request->ip();
        $userAgent = (string) $request->userAgent();

        $user = $this->findUserByEmail($email);

        // Branchless-ish: always perform a hash to keep timing roughly even
        // between known and unknown email paths and avoid leaking enumeration.
        PasswordResetToken::hashPlainToken(Str::random(64));

        if ($user instanceof User) {
            // Invalidate any prior unconsumed tokens for this user so only the
            // freshly emailed link works going forward.
            PasswordResetToken::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $plainToken = Str::random(64);
            PasswordResetToken::query()->create([
                'user_id' => $user->id,
                'email' => $user->email,
                'token_hash' => PasswordResetToken::hashPlainToken($plainToken),
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
                'consumed_at' => null,
                'requested_ip' => $ip,
                'requested_user_agent' => Str::limit($userAgent, 1000, ''),
            ]);

            $resetUrl = rtrim((string) config('app.frontend_url'), '/')
                .'/reset-password/'.$plainToken;

            try {
                Mail::to($user->email)->queue(new PasswordResetMail(
                    $resetUrl,
                    self::EXPIRES_IN_MINUTES,
                    $ip,
                ));
            } catch (\Throwable $e) {
                report($e);
                Log::warning('auth.password_reset.mail_queue_failed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $token = (string) $request->validated('token');
        $email = strtolower(trim((string) $request->validated('email')));
        $password = (string) $request->validated('password');

        $user = DB::transaction(function () use ($token, $email, $password): User {
            $row = PasswordResetToken::query()
                ->where('token_hash', PasswordResetToken::hashPlainToken($token))
                ->lockForUpdate()
                ->first();

            if (! $row instanceof PasswordResetToken || ! $row->isUsable()) {
                throw ValidationException::withMessages([
                    'token' => [__('This password reset link is invalid or has expired.')],
                ]);
            }

            if (strtolower((string) $row->email) !== $email) {
                throw ValidationException::withMessages([
                    'email' => [__('This password reset link is invalid or has expired.')],
                ]);
            }

            $user = $row->user()->lockForUpdate()->first();
            if (! $user instanceof User) {
                throw ValidationException::withMessages([
                    'token' => [__('This password reset link is invalid or has expired.')],
                ]);
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $row->forceFill(['consumed_at' => now()])->save();

            // Invalidate any sibling unconsumed reset tokens for this user.
            PasswordResetToken::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            // Strict: log out every existing session for this user. Sessions
            // live in the DB session driver (config/session.php => database).
            DB::table('sessions')->where('user_id', $user->id)->delete();

            return $user;
        });

        event(new PasswordReset($user));

        try {
            Mail::to($user->email)->queue(new PasswordChangedMail(
                $request->ip(),
                Str::limit((string) $request->userAgent(), 200, ''),
                now()->toIso8601String(),
            ));
        } catch (\Throwable $e) {
            report($e);
            Log::warning('auth.password_changed.mail_queue_failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
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
