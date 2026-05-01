<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserVotingIdAuditResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserVotingIdAuditController extends Controller
{
    public function index(Request $request, User $user): AnonymousResourceCollection
    {
        $this->authorize('users.update');

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $paginator = $user->votingIdAudits()
            ->with('changedBy')
            ->paginate($perPage);

        return UserVotingIdAuditResource::collection($paginator);
    }
}
