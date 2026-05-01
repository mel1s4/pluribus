<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserPersonificationAudit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class PersonificationSession
{
    public static function touchIntervalSeconds(): int
    {
        return max(5, (int) config('personification.touch_interval_seconds', 30));
    }

    public const SESSION_KEY = 'personification';

    public static function maxSeconds(): int
    {
        return max(60, (int) config('personification.max_seconds', 4 * 3600));
    }

    public static function idleSeconds(): int
    {
        return max(60, (int) config('personification.idle_seconds', 30 * 60));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function payload(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $raw = $request->session()->get(self::SESSION_KEY);

        return is_array($raw) ? $raw : null;
    }

    public static function forget(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function expiresAtFromPayload(array $payload): CarbonImmutable
    {
        $started = (int) ($payload['started_at'] ?? 0);

        return CarbonImmutable::createFromTimestampUTC($started)->addSeconds(self::maxSeconds());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function isWallClockExpired(array $payload): bool
    {
        return now()->greaterThan(self::expiresAtFromPayload($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function isIdleExpired(array $payload): bool
    {
        $last = (int) ($payload['last_activity_at'] ?? $payload['started_at'] ?? 0);
        if ($last === 0) {
            return true;
        }

        return now()->greaterThan(CarbonImmutable::createFromTimestampUTC($last)->addSeconds(self::idleSeconds()));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function touch(Request $request, array $payload): void
    {
        $now = now()->timestamp;
        $last = (int) ($payload['last_activity_at'] ?? $payload['started_at'] ?? 0);
        if ($last > 0 && ($now - $last) < self::touchIntervalSeconds()) {
            return;
        }

        $payload['last_activity_at'] = $now;
        $request->session()->put(self::SESSION_KEY, $payload);
    }

    public static function logExpired(Request $request, array $payload, string $reason = 'idle'): void
    {
        $actorId = (int) ($payload['actor_user_id'] ?? 0);
        $targetId = (int) ($payload['target_user_id'] ?? 0);
        if ($actorId > 0 && $targetId > 0) {
            UserPersonificationAudit::query()->create([
                'actor_user_id' => $actorId,
                'target_user_id' => $targetId,
                'action' => $reason === 'idle'
                    ? UserPersonificationAudit::ACTION_EXPIRED_IDLE
                    : UserPersonificationAudit::ACTION_EXPIRED,
                'reason' => $reason === 'idle' ? 'Idle timeout' : 'Maximum duration reached',
                'ticket_reference' => isset($payload['ticket_reference']) ? (string) $payload['ticket_reference'] : null,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            ]);
        }
    }

    public static function obfuscateEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || ! str_contains($email, '@')) {
            return '***';
        }
        [$local, $domain] = explode('@', $email, 2);
        $local = (string) $local;
        $domain = (string) $domain;
        if ($local === '') {
            return '***@'.$domain;
        }
        $visible = strlen($local) <= 2 ? substr($local, 0, 1).'***' : substr($local, 0, 2).'***';

        return $visible.'@'.$domain;
    }

    /**
     * @return array<string, mixed>
     */
    public static function summaryForRequest(Request $request): array
    {
        $actor = $request->attributes->get('personification.actor');
        $payload = $request->attributes->get('personification.payload');
        if (! $actor instanceof User || ! is_array($payload)) {
            return ['active' => false];
        }

        return [
            'active' => true,
            'actor' => [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
            ],
            'started_at' => CarbonImmutable::createFromTimestampUTC((int) ($payload['started_at'] ?? 0))->toIso8601String(),
            'ends_at' => self::expiresAtFromPayload($payload)->toIso8601String(),
        ];
    }
}
