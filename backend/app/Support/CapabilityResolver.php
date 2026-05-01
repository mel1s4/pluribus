<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CapabilityResolver
{
    /** @var array<string, mixed>|null */
    private static ?array $byType = null;

    /** @var list<string>|null */
    private static ?array $catalogIds = null;

    /**
     * @return list<string>
     */
    public function forUser(User $user): array
    {
        $byType = $this->loadByType();
        $capabilities = [];

        if ($user->isRoot()) {
            return $this->capabilitiesForRoleKey('root', $byType);
        }

        if ($user->user_type === 'admin') {
            $capabilities = array_merge($capabilities, $this->capabilitiesForRoleKey('admin', $byType));
        }

        $membershipRole = $this->resolveMembershipRole($user);
        if ($membershipRole !== null) {
            $capabilities = array_merge($capabilities, $this->capabilitiesForRoleKey($membershipRole, $byType));
        }

        $capabilities = array_values(array_unique($capabilities));

        // Platform admins (user_type) need global user list access; do not grant via membership `admin` bundle.
        if (! $user->isRoot() && $user->user_type === 'admin' && ! in_array('users.view', $capabilities, true)) {
            $capabilities[] = 'users.view';
        }

        return $capabilities;
    }

    public function userHasCapability(User $user, string $capabilityId): bool
    {
        return in_array($capabilityId, $this->forUser($user), true);
    }

    /**
     * @return list<string>
     */
    public function allCatalogCapabilityIds(): array
    {
        if (self::$catalogIds !== null) {
            return self::$catalogIds;
        }

        $path = database_path('data/capabilities.json');
        $catalog = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        $ids = [];
        foreach ($catalog as $entry) {
            foreach ($entry['capabilities'] as $cap) {
                $ids[] = (string) $cap['id'];
            }
        }
        self::$catalogIds = $ids;

        return self::$catalogIds;
    }

    /**
     * @param  array<string, mixed>  $byType
     * @return list<string>
     */
    private function capabilitiesForRoleKey(string $roleKey, array $byType): array
    {
        if (! isset($byType[$roleKey]) || ! is_array($byType[$roleKey])) {
            return isset($byType['member']['capabilities']) && is_array($byType['member']['capabilities'])
                ? array_values($byType['member']['capabilities'])
                : [];
        }

        $caps = $byType[$roleKey]['capabilities'] ?? [];

        return is_array($caps) ? array_values($caps) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadByType(): array
    {
        if (self::$byType !== null) {
            return self::$byType;
        }

        $path = database_path('data/user_type_capabilities.json');
        self::$byType = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        return self::$byType;
    }

    private function resolveMembershipRole(User $user): ?string
    {
        /** @var Request|null $request */
        $request = app()->bound('request') ? app('request') : null;
        $communityId = null;
        if ($request !== null) {
            $active = $request->attributes->get('active_community');
            if ($active instanceof \App\Models\Community) {
                $communityId = (int) $active->id;
            }
        }

        if ($communityId === null) {
            $communityId = (int) $user->communities()->orderBy('communities.id')->value('communities.id');
        }
        if ($communityId <= 0) {
            return null;
        }

        $role = $user->communities()
            ->where('communities.id', $communityId)
            ->value('community_user.role');

        return is_string($role) && $role !== '' ? $role : null;
    }
}
