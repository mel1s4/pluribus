<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_numbers' => $validated['phone_numbers'],
            'contact_emails' => $validated['contact_emails'],
            'aliases' => $validated['aliases'],
            'external_links' => $validated['external_links'],
        ];

        $username = $validated['username'] ?? null;
        $data['username'] = ($username !== '' && $username !== null) ? $username : null;

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->fill($data);
        $user->save();

        return response()->json([
            'user' => UserResource::make($user->fresh()),
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $this->authorize('profile.update');

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $disk = Storage::disk('public');

        if ($user->avatar_path) {
            $disk->delete($user->avatar_path);
        }

        $file = $validated['avatar'];
        $ext = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg';
        $basename = Str::uuid()->toString().'.'.$ext;
        $path = $file->storeAs('avatars/'.$user->id, $basename, 'public');

        $user->avatar_path = $path;
        $user->save();

        return response()->json([
            'user' => UserResource::make($user->fresh()),
        ]);
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        $this->authorize('profile.update');

        /** @var User $user */
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        return response()->json([
            'user' => UserResource::make($user->fresh()),
        ]);
    }
}
