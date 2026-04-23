<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSingletonCommunityRequest;
use App\Http\Resources\CommunityLeaderResource;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CommunitySettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $community = Community::current();

        return response()->json([
            'community' => new CommunityResource($community),
        ]);
    }

    /**
     * Public name and logo for unauthenticated pages and client shell branding.
     */
    public function branding(Request $request): JsonResponse
    {
        $community = Community::current();
        $payload = (new CommunityResource($community))->toArray($request);

        return response()->json([
            'community' => Arr::only($payload, ['name', 'logo_url']),
        ]);
    }

    /**
     * Root and community-facing roles for the Leadership tab (no email).
     */
    public function leadership(Request $request): JsonResponse
    {
        $this->authorize('profile.view');

        $leaders = User::query()
            ->where(function ($q): void {
                $q->where('is_root', true)
                    ->orWhereIn('user_type', ['admin', 'developer']);
            })
            ->orderByDesc('is_root')
            ->orderByRaw("case when user_type = 'admin' then 0 when user_type = 'developer' then 1 else 2 end")
            ->orderBy('name')
            ->get();

        return response()->json([
            'leaders' => CommunityLeaderResource::collection($leaders),
        ]);
    }

    public function update(UpdateSingletonCommunityRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor?->isRoot()) {
            abort(403, 'Only root can update community settings.');
        }

        $community = Community::current();
        $validated = $request->validated();
        $previousLogo = $community->logo;
        $nextLogo = $previousLogo;

        if ($request->hasFile('logo_upload')) {
            $this->deleteStoredCommunityLogo($previousLogo);
            $nextLogo = $request->file('logo_upload')->store('community', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteStoredCommunityLogo($previousLogo);
            $nextLogo = null;
        } elseif (array_key_exists('logo', $validated)) {
            $incoming = $validated['logo'];
            if ($incoming === null || $incoming === '') {
                $this->deleteStoredCommunityLogo($previousLogo);
                $nextLogo = null;
            } else {
                if ($previousLogo !== $incoming) {
                    $this->deleteStoredCommunityLogo($previousLogo);
                }
                $nextLogo = $incoming;
            }
        }

        $community->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'rules' => $validated['rules'] ?? null,
            'logo' => $nextLogo,
        ]);
        $community->save();

        return response()->json([
            'community' => new CommunityResource($community->fresh()),
        ]);
    }

    private function deleteStoredCommunityLogo(?string $logo): void
    {
        if ($logo === null || $logo === '' || $this->isRemoteCommunityLogo($logo)) {
            return;
        }
        Storage::disk('public')->delete($logo);
    }

    private function isRemoteCommunityLogo(string $logo): bool
    {
        return preg_match('#^https?://#i', $logo) === 1;
    }
}
