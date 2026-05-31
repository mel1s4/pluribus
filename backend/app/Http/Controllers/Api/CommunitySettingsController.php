<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCommunityCurrencyRequest;
use App\Http\Requests\UpdateCommunityLegalDocumentsRequest;
use App\Http\Requests\UpdateSingletonCommunityRequest;
use App\Http\Resources\CommunityLeaderResource;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CommunitySettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $community = $this->resolveTargetCommunity($request);

        return response()->json([
            'community' => new CommunityResource($community),
        ]);
    }

    /**
     * Public name and logo for unauthenticated pages and client shell branding.
     */
    public function branding(Request $request): JsonResponse
    {
        $community = $this->resolveTargetCommunity($request);
        $payload = (new CommunityResource($community))->toArray($request);

        return response()->json([
            'community' => Arr::only($payload, [
                'name',
                'logo_url',
                'default_language',
                'currency_code',
                'currency_name',
                'local_currency_code',
                'latitude',
                'longitude',
            ]),
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

        $community = $this->resolveTargetCommunity($request);
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

        $fill = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'rules' => $validated['rules'] ?? null,
            'logo' => $nextLogo,
            'default_language' => $validated['default_language'] ?? $community->default_language,
        ];
        if (array_key_exists('latitude', $validated)) {
            $fill['latitude'] = $validated['latitude'];
        }
        if (array_key_exists('longitude', $validated)) {
            $fill['longitude'] = $validated['longitude'];
        }
        if (array_key_exists('currency_code', $validated)) {
            $fill['currency_code'] = $validated['currency_code'];
        }
        if (array_key_exists('currency_name', $validated)) {
            $fill['currency_name'] = $validated['currency_name'];
        }
        if (array_key_exists('local_currency_code', $validated)) {
            $fill['local_currency_code'] = $validated['local_currency_code'];
        }
        $community->fill($fill);
        $community->save();

        return response()->json([
            'community' => new CommunityResource($community->fresh()),
        ]);
    }

    public function updateCurrency(UpdateCommunityCurrencyRequest $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            abort(401);
        }
        if (! $actor->isRoot() && $actor->user_type !== 'admin') {
            abort(403, 'Only root or community admins can update currency.');
        }

        $community = $this->resolveTargetCommunity($request);
        $validated = $request->validated();
        if (
            ! array_key_exists('currency_code', $validated)
            && ! array_key_exists('currency_name', $validated)
            && ! array_key_exists('local_currency_code', $validated)
        ) {
            throw ValidationException::withMessages([
                'currency_code' => [__('Provide currency_code, currency_name, and/or local_currency_code.')],
            ]);
        }
        if (array_key_exists('currency_code', $validated)) {
            $community->currency_code = $validated['currency_code'];
        }
        if (array_key_exists('currency_name', $validated)) {
            $community->currency_name = $validated['currency_name'];
        }
        if (array_key_exists('local_currency_code', $validated)) {
            $community->local_currency_code = $validated['local_currency_code'];
        }
        $community->save();

        return response()->json([
            'community' => new CommunityResource($community->fresh()),
        ]);
    }

    public function updateLegalDocuments(UpdateCommunityLegalDocumentsRequest $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            abort(401);
        }
        if (! $actor->isRoot() && $actor->user_type !== 'admin') {
            abort(403, 'Only root or community admins can update legal documents.');
        }

        $community = $this->resolveTargetCommunity($request);
        $validated = $request->validated();
        if (! array_key_exists('terms_markdown', $validated) && ! array_key_exists('privacy_policy_markdown', $validated)) {
            throw ValidationException::withMessages([
                'terms_markdown' => [__('Provide terms_markdown and/or privacy_policy_markdown.')],
            ]);
        }
        if (array_key_exists('terms_markdown', $validated)) {
            $community->terms_markdown = $validated['terms_markdown'];
        }
        if (array_key_exists('privacy_policy_markdown', $validated)) {
            $community->privacy_policy_markdown = $validated['privacy_policy_markdown'];
        }
        $community->save();

        return response()->json([
            'community' => new CommunityResource($community->fresh()),
        ]);
    }

    private function resolveTargetCommunity(Request $request): Community
    {
        $active = $request->attributes->get('active_community');

        return $active instanceof Community ? $active : Community::current();
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
