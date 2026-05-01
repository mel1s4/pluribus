<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolvePersonificationUserRequest;
use App\Http\Requests\StartPersonificationRequest;
use App\Models\User;
use App\Models\UserPersonificationAudit;
use App\Support\PersonificationSession;
use App\Support\PersonifyPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PersonificationController extends Controller
{
    public function resolve(ResolvePersonificationUserRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }
        PersonifyPolicy::assertActorMayPersonify($actor);

        $email = trim((string) $request->input('email', ''));
        $username = trim((string) $request->input('username', ''));

        $query = User::query();
        if ($email !== '') {
            $query->whereRaw('lower(email) = ?', [mb_strtolower($email)]);
        } else {
            $query->where('username', $username);
        }

        $target = $query->first();
        if ($target === null) {
            return response()->json(['message' => 'No matching user was found.'], 404);
        }

        PersonifyPolicy::assertValidTarget($actor, $target);

        return response()->json([
            'user' => [
                'id' => $target->id,
                'name' => $target->name,
                'email_obfuscated' => PersonificationSession::obfuscateEmail((string) $target->email),
            ],
        ]);
    }

    public function start(StartPersonificationRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }
        PersonifyPolicy::assertActorMayPersonify($actor);

        if (PersonificationSession::payload($request) !== null) {
            throw ValidationException::withMessages([
                'target_user_id' => [__('End the current personification session before starting another.')],
            ]);
        }

        $target = User::query()->findOrFail((int) $request->validated('target_user_id'));
        PersonifyPolicy::assertValidTarget($actor, $target);

        if (! Hash::check($request->validated('password'), (string) $actor->password)) {
            throw ValidationException::withMessages([
                'password' => [__('The provided password is incorrect.')],
            ]);
        }

        $reason = trim((string) $request->validated('reason'));
        if (! PersonifyPolicy::reasonIsMeaningful($reason)) {
            throw ValidationException::withMessages([
                'reason' => ['Please provide a specific support or investigation reason (at least 12 characters, not a generic placeholder).'],
            ]);
        }

        $now = now()->timestamp;
        $ticket = $request->validated('ticket_reference');
        $ticket = $ticket !== null ? trim((string) $ticket) : null;
        if ($ticket === '') {
            $ticket = null;
        }

        $payload = [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'started_at' => $now,
            'last_activity_at' => $now,
            'reason' => $reason,
            'ticket_reference' => $ticket,
        ];
        $request->session()->put(PersonificationSession::SESSION_KEY, $payload);

        UserPersonificationAudit::query()->create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'action' => UserPersonificationAudit::ACTION_STARTED,
            'reason' => $reason,
            'ticket_reference' => $ticket,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
        ]);

        return response()->json([
            'ok' => true,
            'personification' => [
                'active' => true,
                'target' => [
                    'id' => $target->id,
                    'name' => $target->name,
                ],
            ],
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $payload = PersonificationSession::payload($request);
        if ($payload === null) {
            return response()->json(['ok' => true, 'active' => false]);
        }

        if ((int) ($payload['actor_user_id'] ?? 0) !== (int) $actor->id) {
            PersonificationSession::forget($request);

            return response()->json(['ok' => true, 'active' => false]);
        }

        $targetId = (int) ($payload['target_user_id'] ?? 0);
        UserPersonificationAudit::query()->create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $targetId > 0 ? $targetId : null,
            'action' => UserPersonificationAudit::ACTION_STOPPED,
            'reason' => 'Session ended by operator',
            'ticket_reference' => isset($payload['ticket_reference']) ? (string) $payload['ticket_reference'] : null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
        ]);

        PersonificationSession::forget($request);

        return response()->json(['ok' => true, 'active' => false]);
    }

    public function status(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $payload = PersonificationSession::payload($request);
        if ($payload === null || (int) ($payload['actor_user_id'] ?? 0) !== (int) $actor->id) {
            return response()->json(['active' => false]);
        }

        $target = User::query()->find((int) ($payload['target_user_id'] ?? 0));
        if ($target === null) {
            PersonificationSession::forget($request);

            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'actor' => [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
            ],
            'target' => [
                'id' => $target->id,
                'name' => $target->name,
            ],
            'started_at' => \Carbon\CarbonImmutable::createFromTimestampUTC((int) ($payload['started_at'] ?? 0))->toIso8601String(),
            'ends_at' => PersonificationSession::expiresAtFromPayload($payload)->toIso8601String(),
        ]);
    }
}
