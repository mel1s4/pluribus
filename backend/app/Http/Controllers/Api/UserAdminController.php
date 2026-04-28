<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserAdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $query = User::query();
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $paginator = $query
            ->orderBy('id')
            ->paginate($perPage);

        return UserSummaryResource::collection($paginator);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('users.create');

        $validated = $request->validated();
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'username' => $validated['username'] ?? null,
            'phone_numbers' => $validated['phone_numbers'],
            'contact_emails' => $validated['contact_emails'],
            'aliases' => $validated['aliases'],
            'external_links' => $validated['external_links'],
            'user_type' => 'member',
            'is_root' => false,
        ];

        if ($request->user()?->can('users.assign_types')) {
            if (array_key_exists('user_type', $validated)) {
                $data['user_type'] = $validated['user_type'];
            }
            if (array_key_exists('is_root', $validated) && $validated['is_root'] === true) {
                $data['is_root'] = true;
                $data['user_type'] = 'root';
            }
        }

        $user = User::query()->create($data);

        return response()->json([
            'user' => new UserSummaryResource($user),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserSummaryResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('users.update');

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        if ($user->isRoot() && ! $actor->isRoot()) {
            abort(403, 'Only root can modify a root account.');
        }

        $validated = $request->validated();
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (array_key_exists('username', $validated)) {
            $u = $validated['username'];
            $data['username'] = ($u !== '' && $u !== null) ? $u : null;
        }

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        if (array_key_exists('phone_numbers', $validated)) {
            $data['phone_numbers'] = $validated['phone_numbers'];
        }
        if (array_key_exists('contact_emails', $validated)) {
            $data['contact_emails'] = $validated['contact_emails'];
        }
        if (array_key_exists('aliases', $validated)) {
            $data['aliases'] = $validated['aliases'];
        }
        if (array_key_exists('external_links', $validated)) {
            $data['external_links'] = $validated['external_links'];
        }

        if ($request->user()?->can('users.assign_types')) {
            if (array_key_exists('is_root', $validated)) {
                $wantRoot = (bool) $validated['is_root'];
                if ($user->isRoot() && ! $wantRoot) {
                    if (! $actor->isRoot()) {
                        abort(403, 'Only root can remove the root flag.');
                    }
                    $rootCount = User::query()->where('is_root', true)->count();
                    if ($rootCount <= 1) {
                        abort(403, 'Cannot remove root flag from the last root account.');
                    }
                }
                $data['is_root'] = $wantRoot;
                if ($wantRoot) {
                    $data['user_type'] = 'root';
                } elseif ($user->isRoot()) {
                    $data['user_type'] = $validated['user_type'] ?? 'member';
                }
            }
            if (array_key_exists('user_type', $validated)) {
                $effectiveRoot = array_key_exists('is_root', $validated)
                    ? (bool) $validated['is_root']
                    : $user->isRoot();
                if (! $effectiveRoot) {
                    $data['user_type'] = $validated['user_type'];
                }
            }
        }

        $user->fill($data);
        $user->save();

        return response()->json([
            'user' => new UserSummaryResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('users.delete');

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        if ($actor->id === $user->id) {
            abort(403, 'You cannot delete your own account.');
        }

        if ($user->isRoot() && ! $actor->isRoot()) {
            abort(403, 'Only root can delete a root account.');
        }

        if ($user->isRoot()) {
            $rootCount = User::query()->where('is_root', true)->count();
            if ($rootCount <= 1) {
                abort(403, 'Cannot delete the last root account.');
            }
        }

        $user->delete();

        return response()->json(['ok' => true]);
    }
}
