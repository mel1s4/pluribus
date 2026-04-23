<?php

namespace App\Support;

use App\Models\User;
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
        if ($user->isRoot()) {
            return $this->capabilitiesForRoleKey('root', $byType);
        }

        $role = $user->user_type ?: 'member';

        return $this->capabilitiesForRoleKey($role, $byType);
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
}
