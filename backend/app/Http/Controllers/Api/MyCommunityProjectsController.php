<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityProjectResource;
use App\Models\CommunityProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MyCommunityProjectsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $userId = (int) $user->id;

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $paginator = CommunityProject::query()
            ->where('proposer_id', $userId)
            ->withSum('budgetItems', 'subtotal')
            ->with(['community', 'proposer:id,name,avatar_path'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return CommunityProjectResource::collection($paginator);
    }
}
