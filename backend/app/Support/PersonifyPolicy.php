<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class PersonifyPolicy
{
    public static function actorMayPersonify(User $actor): bool
    {
        if ($actor->isRoot()) {
            return true;
        }

        return app(CapabilityResolver::class)->userHasCapability($actor, 'users.personify');
    }

    public static function assertActorMayPersonify(User $actor): void
    {
        if (! self::actorMayPersonify($actor)) {
            abort(403, 'Personification is not allowed for this account.');
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertValidTarget(User $actor, User $target): void
    {
        if ((int) $actor->id === (int) $target->id) {
            throw ValidationException::withMessages([
                'target_user_id' => ['You cannot personify your own account.'],
            ]);
        }

        if ($target->isRoot()) {
            throw ValidationException::withMessages([
                'target_user_id' => ['Root accounts cannot be personified.'],
            ]);
        }

        if (! $actor->isRoot() && $target->isCommunityAdministrator()) {
            abort(403, 'Developers cannot personify community administrator accounts.');
        }
    }

    public static function reasonIsMeaningful(string $reason): bool
    {
        $t = trim($reason);

        return strlen($t) >= 12 && ! in_array(strtolower($t), [
            'support',
            'testing',
            'debug',
            'debugging',
            'test',
            'asdf',
            'reason',
            'ticket',
        ], true);
    }
}
