<?php

namespace App\Support;

use App\Models\Community;
use App\Models\CommunityDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityDomainSync
{
    /**
     * @param  list<array{host?: string, is_primary?: bool}>|null  $rows
     */
    public static function sync(Community $community, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        DB::transaction(function () use ($community, $rows): void {
            $normalized = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $host = CommunityDomain::normalizeHost((string) ($row['host'] ?? ''));
                if ($host === '') {
                    continue;
                }
                if (CommunityHost::isPlatformHost($host)) {
                    throw ValidationException::withMessages([
                        'domains' => [__('This host is reserved for the platform.')],
                    ]);
                }
                $collision = CommunityDomain::query()
                    ->where('host', $host)
                    ->where('community_id', '!=', $community->id)
                    ->exists();
                if ($collision) {
                    throw ValidationException::withMessages([
                        'domains' => [__('The host :host is already used by another community.', ['host' => $host])],
                    ]);
                }
                $normalized[$host] = (bool) ($row['is_primary'] ?? false);
            }

            $community->domains()->whereNotIn('host', array_keys($normalized))->delete();

            $primaryHost = null;
            foreach ($normalized as $host => $isPrimary) {
                if ($isPrimary) {
                    $primaryHost = $host;
                    break;
                }
            }
            if ($primaryHost === null && $normalized !== []) {
                $primaryHost = array_key_first($normalized);
            }

            foreach ($normalized as $host => $_isPrimary) {
                CommunityDomain::query()->updateOrCreate(
                    [
                        'community_id' => $community->id,
                        'host' => $host,
                    ],
                    [
                        'is_primary' => $host === $primaryHost,
                        'verified_at' => now(),
                    ]
                );
            }

            if ($primaryHost !== null) {
                $community->domains()
                    ->where('host', '!=', $primaryHost)
                    ->update(['is_primary' => false]);
            }
        });
    }
}
