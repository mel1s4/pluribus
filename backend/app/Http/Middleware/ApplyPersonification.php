<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PersonificationSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApplyPersonification
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $actor = $request->user();
        if (! $actor instanceof User) {
            if (PersonificationSession::payload($request) !== null) {
                PersonificationSession::forget($request);
            }

            return $next($request);
        }

        $payload = PersonificationSession::payload($request);
        if ($payload === null) {
            return $next($request);
        }

        if ((int) ($payload['actor_user_id'] ?? 0) !== (int) $actor->id) {
            PersonificationSession::forget($request);

            return $next($request);
        }

        if (PersonificationSession::isWallClockExpired($payload)) {
            PersonificationSession::logExpired($request, $payload, 'wall');
            PersonificationSession::forget($request);

            return $next($request);
        }

        if (PersonificationSession::isIdleExpired($payload)) {
            PersonificationSession::logExpired($request, $payload, 'idle');
            PersonificationSession::forget($request);

            return $next($request);
        }

        $target = User::query()->find((int) ($payload['target_user_id'] ?? 0));
        if ($target === null) {
            PersonificationSession::forget($request);

            return $next($request);
        }

        PersonificationSession::touch($request, $payload);
        $payload = PersonificationSession::payload($request) ?? $payload;

        Auth::setUser($target);
        $request->setUserResolver(static fn () => $target);
        $request->attributes->set('personification.actor', $actor);
        $request->attributes->set('personification.target', $target);
        $request->attributes->set('personification.payload', $payload);
        Log::debug('personification.middleware.applied', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'path' => $request->path(),
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $next($request);
    }
}
