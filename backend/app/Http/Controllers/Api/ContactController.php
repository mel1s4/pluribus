<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchContactByEmailRequest;
use App\Http\Requests\StoreUserContactRequest;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use App\Models\UserContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $contacts = $user->contacts()
            ->orderBy('name')
            ->get();

        return response()->json([
            'contacts' => UserSummaryResource::collection($contacts),
        ]);
    }

    public function search(SearchContactByEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        $email = strtolower((string) $request->validated('email'));

        $match = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->where('id', '!=', $user->id)
            ->first();

        return response()->json([
            'user' => $match ? new UserSummaryResource($match) : null,
        ]);
    }

    public function store(StoreUserContactRequest $request): JsonResponse
    {
        $user = $request->user();
        $contactUserId = (int) $request->validated('contact_user_id');

        $contact = UserContact::query()->firstOrCreate([
            'user_id' => (int) $user->id,
            'contact_user_id' => $contactUserId,
        ]);

        $contactUser = User::query()->findOrFail($contact->contact_user_id);

        return response()->json([
            'contact' => new UserSummaryResource($contactUser),
        ], $contact->wasRecentlyCreated ? 201 : 200);
    }
}
